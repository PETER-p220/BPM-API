<?php

namespace App\Http\Controllers;

use App\Models\ProjectAnalysis;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\JsonResponse;
use App\Models\Tender;
use App\Models\Department;
use Illuminate\Support\Facades\Log;
use Cloudinary\Cloudinary;
use Illuminate\Validation\ValidationException;

class ProjectAnalysisController extends Controller
{
    protected $cloudinary;

    public function __construct()
    {
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => config('cloudinary.cloud.cloud_name'),
                'api_key' => config('cloudinary.cloud.api_key'),
                'api_secret' => config('cloudinary.cloud.api_secret'),
            ],
            'url' => [
                'secure' => true,
            ],
        ]);
    }

    // Retrieve all project analyses
    public function index(): JsonResponse
    {
        try {
            $analyses = ProjectAnalysis::with(['user', 'department', 'tender'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $analyses
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching project analyses: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch project analyses.'
            ], 500);
        }
    }

    // Get project analyses created by the logged-in user
    public function myAnalyses()
    {
        try {
            $analyses = ProjectAnalysis::with('user', 'department', 'tender')
                ->where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->get();

            if ($analyses->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No analyses found for the logged-in user.',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Analyses retrieved successfully.',
                'data' => $analyses,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch analyses.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Retrieve a specific project analysis
    public function show($analysis_id)
    {
        $analysis = ProjectAnalysis::with(['user', 'department', 'tender'])->find($analysis_id);

        if (!$analysis) {
            return response()->json(['status' => 'error', 'message' => 'Analysis not found'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $analysis]);
    }

    public function store(Request $request)
{
    try {
        // Validate input
        $validatedData = $request->validate([
            'tender_id' => 'required|integer',
            'department_id' => 'required|integer',
            'analysis_file' => 'required|max:10240',
            'amount_required_for_request' => 'required|numeric',
        ]);

        // Check if analysis already exists for this tender_id
        $existingAnalysis = ProjectAnalysis::where('tender_id', $validatedData['tender_id'])->first();
        if ($existingAnalysis) {
            return response()->json([
                'status' => 'error',
                'message' => 'A project analysis already submitted for this tender',
                'existing_analysis' => $existingAnalysis
            ], 409); // 409 Conflict status code
        }

        // Upload the analysis file to Cloudinary
        $fileUrl = $this->uploadAnalysisFile($request);

        // Create the project analysis
        $analysis = ProjectAnalysis::create([
            'tender_id' => $validatedData['tender_id'],
            'user_id' => Auth::id(),
            'department_id' => $validatedData['department_id'],
            'analysis_file' => $fileUrl,
            'amount_required_for_request' => $validatedData['amount_required_for_request'],
            'status' => 'pending',
        ]);

        // Get the name of the logged-in user
        $user = Auth::user();
        $createdAt = $analysis->created_at->timezone('Africa/Nairobi')->format('Y-m-d H:i:s');

        // Notify the admin about the new project analysis
        $adminUser = User::where('role_id', 1)->first();
        if ($adminUser) {
            Mail::raw("A new project analysis has been submitted by {$user->name} on {$createdAt}. Please review the analysis.", function ($message) use ($adminUser) {
                $message->to($adminUser->email)
                        ->subject("New Project Analysis Submitted");
            });
        }

        return response()->json(['status' => 'success', 'message' => 'Project analysis created successfully', 'data' => $analysis], 201);

    } catch (ValidationException $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);
    } catch (\Exception $e) {
        \Log::error('Error during project analysis creation: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => 'An error occurred while creating project analysis',
            'error' => $e->getMessage(),
        ], 500);
    }
}

// Handle file upload for analysis file
private function uploadAnalysisFile(Request $request)
{
    if ($request->hasFile('analysis_file')) {
        $file = $request->file('analysis_file');

        // Check if the file is a ZIP file and set the appropriate resource type
        $resourceType = $file->getClientMimeType() === 'application/zip' ? 'raw' : 'auto';

        // Upload the file to Cloudinary
        $uploadResult = $this->cloudinary->uploadApi()->upload($file->getRealPath(), [
            'folder' => 'project_analysis_files/',  // Customize the folder name
            'public_id' => time() . '_' . $file->getClientOriginalName(), // Custom file name
            'resource_type' => $resourceType,  // Set the resource type for ZIP files or auto-detect
        ]);

        // Log the result as an array instead of an object
        \Log::info('Cloudinary Upload Result for Analysis File:', (array) $uploadResult);

        // Get the secure URL of the uploaded file
        $analysisFileUrl = $uploadResult['secure_url'];

        // Return the Cloudinary URL
        return $analysisFileUrl;
    }

    return null;  // If no file was uploaded, return null
}


    // Update a project analysis
    public function update(Request $request, $analysis_id)
    {
        $analysis = ProjectAnalysis::findOrFail($analysis_id);

        $validatedData = $request->validate([
            'status' => 'nullable|string|in:pending,rejected,passed',
            'reason_for_reject' => 'nullable|string',
        ]);

        $analysis->update($validatedData);

        // Notify the user if the analysis is updated to rejected or approved
        if (!empty($validatedData['status']) && in_array($validatedData['status'], ['rejected', 'passed'])) {
            $analysisOwner = User::find($analysis->user_id);
            if ($analysisOwner) {
                Mail::raw("Your project analysis has been {$validatedData['status']}.", function ($message) use ($analysisOwner, $validatedData) {
                    $message->to($analysisOwner->email)
                            ->subject("Project Analysis Status Update");
                });
            }
        }

        return response()->json(['status' => 'success', 'message' => 'Project analysis updated successfully', 'data' => $analysis]);
    }

    // Delete a project analysis
    public function destroy($analysis_id)
    {
        $analysis = ProjectAnalysis::findOrFail($analysis_id);
        $analysis->delete();

        return response()->json(['status' => 'success', 'message' => 'Project analysis deleted successfully']);
    }


    // Count all project analyses for the logged-in user
public function countAllAnalyses()
{
    $userId = auth()->id(); // Get logged-in user ID

    $totalCount = ProjectAnalysis::where('user_id', $userId)->count();

    return response()->json([
        'status' => true,
        'message' => 'Total analyses counted successfully.',
        'total_count' => $totalCount,
    ], 200);
}

// Count project analyses with status 'passed'
public function countPassedAnalyses()
{
    $userId = auth()->id(); // Get logged-in user ID

    $passedCount = ProjectAnalysis::where('user_id', $userId)
        ->where('status', 'passed')
        ->count();

    return response()->json([
        'status' => true,
        'message' => 'Total passed analyses counted successfully.',
        'passed_count' => $passedCount,
    ], 200);
}

// Count project analyses with status 'rejected'
public function countRejectedAnalyses()
{
    $userId = auth()->id(); // Get logged-in user ID

    $rejectedCount = ProjectAnalysis::where('user_id', $userId)
        ->where('status', 'rejected')
        ->count();

    return response()->json([
        'status' => true,
        'message' => 'Total rejected analyses counted successfully.',
        'rejected_count' => $rejectedCount,
    ], 200);
}


// Count total amount required for requests with status 'passed' for the logged-in user
public function countTotalAmountRequired()
{
    try {
        $userId = auth()->id(); // Get logged-in user ID

        // Sum the amount required for requests for the logged-in user with status 'passed'
        $totalAmountRequired = ProjectAnalysis::where('user_id', $userId)
            ->where('status', 'passed') // Add status condition
            ->sum('amount_required_for_request');

        return response()->json([
            'status' => true,
            'message' => 'Total amount required calculated successfully.',
            'total_amount_required' => $totalAmountRequired,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to calculate total amount required.',
            'error' => $e->getMessage(),
        ], 500);
    }
}




// Count all project analyses
public function countAnalyses()
{
    $totalCount = ProjectAnalysis::count();

    return response()->json([
        'status' => true,
        'message' => 'Total analyses counted successfully.',
        'total_count' => $totalCount,
    ], 200);
}

// Count project analyses with status 'passed'
public function countAllPassedAnalyses()
{
    $passedCount = ProjectAnalysis::where('status', 'passed')->count();

    return response()->json([
        'status' => true,
        'message' => 'Total passed analyses counted successfully.',
        'passed_count' => $passedCount,
    ], 200);
}

// Count project analyses with status 'rejected'
public function countALlRejectedAnalyses()
{
    $rejectedCount = ProjectAnalysis::where('status', 'rejected')->count();

    return response()->json([
        'status' => true,
        'message' => 'Total rejected analyses counted successfully.',
        'rejected_count' => $rejectedCount,
    ], 200);
}

// Count total amount required for requests with status 'passed'
public function countAllTotalAmountRequired()
{
    try {
        // Sum the amount required for requests for all analyses with status 'passed'
        $totalAmountRequired = ProjectAnalysis::where('status', 'passed')
            ->sum('amount_required_for_request');

        return response()->json([
            'status' => true,
            'message' => 'Total amount required calculated successfully.',
            'total_amount_required' => $totalAmountRequired,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to calculate total amount required.',
            'error' => $e->getMessage(),
        ], 500);
    }
}


}

