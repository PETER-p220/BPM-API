<?php

namespace App\Http\Controllers;

use App\Imports\AnalysisImport;
use App\Models\Analysis;
use App\Models\User; 
use App\Models\Project; 
use App\Models\Tender;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use DateTime;
use DateTimeZone;

class AnalysisController extends Controller
{
    public function index()
    {
        try {
            $analyses = Analysis::with(['project', 'tender', 'user'])->get();
            
            return response()->json([
                'status' => 200,
                'data' => $analyses,
                'message' => 'Analysis data retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching analysis data', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'status' => 500,
                'message' => 'Error retrieving analysis data',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function userAnalysis(Request $request)
{
    try {
        $user = Auth::user();
        $analyses = Analysis::with(['project', 'tender', 'user'])
            ->where('user_id', $user->user_id)
            ->get();
        
        return response()->json([
            'status' => 200,
            'data' => $analyses,
            'message' => 'User analysis data retrieved successfully'
        ], 200);
    } catch (\Exception $e) {
        Log::error('Error fetching user analysis', [
            'error' => $e->getMessage(),
            'user_id' => Auth::id()
        ]);
        return response()->json([
            'status' => 500,
            'message' => 'Error retrieving user analysis',
            'error' => $e->getMessage()
        ], 500);
    }
}


public function ItemsDropdown(Request $request)
{
    try {
        $user = Auth::user();
        $query = Analysis::where('user_id', $user->user_id);

        // Filter by project_id if provided
        if ($request->has('project_id') && $request->input('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }

        $analyses = $query->get()->map(function ($analysis) {
            return [
                'analysis_id' => $analysis->analysis_id,
                'items' => [$analysis->item_description ?: 'N/A'] // Handle null item_description
            ];
        });

        return response()->json([
            'status' => 200,
            'data' => $analyses,
            'message' => 'User items dropdown data retrieved successfully'
        ], 200);
    } catch (\Exception $e) {
        Log::error('Error fetching items dropdown', [
            'error' => $e->getMessage(),
            'user_id' => Auth::id()
        ]);
        return response()->json([
            'status' => 500,
            'message' => 'Error retrieving items dropdown',
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function store(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|file|mimes:xlsx,xls',
                'project_id' => 'required|exists:projects,project_id',
            ]);
    
            $file = $request->file('excel_file');
            $importer = new AnalysisImport($request->project_id);
            Excel::import($importer, $file);
    
            $rowCount = Analysis::where('project_id', $request->project_id)
                                ->whereNotNull('serial_number')
                                ->count();
    
            if ($rowCount === 0) {
                throw new \Exception('No meaningful data was imported from the Excel file');
            }
    
            Log::info('Analysis data imported successfully', [
                'user_id' => Auth::id(),
                'project_id' => $request->project_id,
                'row_count' => $rowCount
            ]);
    
            // Get uploader details
            $uploader = Auth::user();
            $uploaderName = $uploader->name;
            $uploaderEmail = $uploader->email;
    
            // Get project details
            $project = \App\Models\Project::find($request->project_id);
            $projectName = $project->project_name ?? 'Unknown Project';
    
            // Notify users with role_id = 1
            $adminUsers = User::where('role_id', 1)
                              ->where('user_id', '!=', Auth::id()) // Exclude uploader if they’re an admin
                              ->get();
    
            $adminSubject = "New Analysis Uploaded for Review: {$projectName}";
            $adminBody = "A new analysis has been uploaded by {$uploaderName} for the project '{$projectName}'.\n"
                       . "Rows imported: {$rowCount}.\n"
                       . "Please log in to the portal to review the details.";
    
            foreach ($adminUsers as $admin) {
                try {
                    Mail::raw($adminBody, function ($message) use ($admin, $adminSubject) {
                        $message->to($admin->email)
                                ->subject($adminSubject);
                    });
                    Log::info('Email sent to admin', ['email' => $admin->email]);
                } catch (\Exception $e) {
                    Log::error('Failed to send email to admin', [
                        'email' => $admin->email,
                        'error' => $e->getMessage()
                    ]);
                }
            }
    
            // Notify the uploader
            $uploaderSubject = "Your Analysis Upload was Successful: {$projectName}";
            $uploaderBody = "Hi {$uploaderName},\n"
                          . "Your analysis for the project '{$projectName}' has been successfully uploaded.\n"
                          . "Rows imported: {$rowCount}.\n"
                          . "The team has been notified for review.";
    
            try {
                Mail::raw($uploaderBody, function ($message) use ($uploaderEmail, $uploaderSubject) {
                    $message->to($uploaderEmail)
                            ->subject($uploaderSubject);
                });
                Log::info('Email sent to uploader', ['email' => $uploaderEmail]);
            } catch (\Exception $e) {
                Log::error('Failed to send email to uploader', [
                    'email' => $uploaderEmail,
                    'error' => $e->getMessage()
                    ]);
            }
    
            return response()->json([
                'status' => 201,
                'message' => 'Analysis data imported successfully',
                'rows_imported' => $rowCount
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error importing analysis data', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
    
            return response()->json([
                'status' => 500,
                'message' => 'Error importing analysis data',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function show($analysis_id)
    {
        try {
            $analysis = Analysis::with(['project', 'tender', 'user'])
                ->findOrFail($analysis_id);

            return response()->json([
                'status' => 200,
                'data' => $analysis,
                'message' => 'Analysis retrieved successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Analysis not found'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error fetching analysis', [
                'analysis_id' => $analysis_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Error retrieving analysis',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function update(Request $request)
    {
        try {
            // Validate the request
            $request->validate([
                'project_id' => 'required|exists:projects,project_id',
                'status' => 'required|in:approved,rejected',
                'reason_for_reject' => 'required_if:status,rejected|nullable|string',
            ]);

            $projectId = $request->input('project_id');
            $status = $request->input('status');
            $reasonForReject = $request->input('reason_for_reject');

            // Check all analyses for the given project_id that are still pending
            $pendingAnalyses = Analysis::where('project_id', $projectId)
                                       ->where('status', 'pending')
                                       ->get();

            if ($pendingAnalyses->isEmpty()) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Analysis for  that project  already approved/rejected cannot  be updated  again please  contact  with administrator'
                ], 404);
            }

            // Update only pending analyses
            $updatedCount = Analysis::where('project_id', $projectId)
                                   ->where('status', 'pending')
                                   ->update([
                                       'status' => $status,
                                       'reason_for_reject' => $status === 'rejected' ? $reasonForReject : null,
                                       'updated_at' => now(),
                                   ]);

            Log::info('Pending analyses updated successfully', [
                'project_id' => $projectId,
                'status' => $status,
                'updated_count' => $updatedCount,
                'user_id' => Auth::id()
            ]);

            // Get project details and uploader (based on pending analyses)
            $project = Project::find($projectId);
            $projectName = $project->project_name ?? 'Unknown Project';
            $uploaderId = $pendingAnalyses->first()->user_id; // Use first pending analysis uploader
            $uploader = User::find($uploaderId);

            if ($uploader) {
                // Format date/time in East Africa Time (EAT, UTC+3)
                $dateTime = new DateTime('now', new DateTimeZone('Africa/Nairobi'));
                $formattedDateTime = $dateTime->format('Y-m-d H:i:s');

                $subject = "Your Analysis has been " . ucfirst($status);
                $body = "Hi {$uploader->name},\n"
                      . "Your analysis for project_name: {$projectName} has been {$status} on {$formattedDateTime} EAT.\n"
                      . "Please log in to the portal for more details.";

                try {
                    Mail::raw($body, function ($message) use ($uploader, $subject) {
                        $message->to($uploader->email)
                                ->subject($subject);
                    });
                    Log::info('Email sent to uploader', ['email' => $uploader->email, 'project_id' => $projectId]);
                } catch (\Exception $e) {
                    Log::error('Failed to send email to uploader', [
                        'email' => $uploader->email,
                        'project_id' => $projectId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'status' => 200,
                'message' => "Updated {$updatedCount} pending analyses successfully",
                'data' => [
                    'project_id' => $projectId,
                    'status' => $status,
                    'reason_for_reject' => $reasonForReject,
                    'updated_count' => $updatedCount
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error updating analyses', [
                'project_id' => $request->input('project_id', 'unknown'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Error updating analyses',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function destroy($analysis_id)
    {
        try {
            $analysis = Analysis::findOrFail($analysis_id);
            $analysis->delete();

            Log::info('Analysis deleted successfully', [
                'analysis_id' => $analysis_id,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Analysis deleted successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Analysis not found'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error deleting analysis', [
                'analysis_id' => $analysis_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Error deleting analysis',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
 * Count distinct project_ids for all user analyses
 */
public function countUserAnalyses()
{
    try {
        $user = Auth::user();
        Log::info('Counting distinct project_ids for user analyses', [
            'user_id' => $user->user_id
        ]);

        $count = Analysis::where('user_id', $user->user_id)
            ->distinct('project_id')
            ->count('project_id');

        return response()->json([
            'status' => 200,
            'total_count' => $count,
            'message' => 'User analyses for project counted successfully'
        ], 200);
    } catch (\Exception $e) {
        Log::error('Error counting user analyses', [
            'error' => $e->getMessage(),
            'user_id' => Auth::id()
        ]);
        return response()->json([
            'status' => 500,
            'message' => 'Error counting user analyses'
        ], 500);
    }
}

/**
 * Count distinct project_ids with approved user analyses
 */
public function countApprovedUserAnalyses()
{
    try {
        $user = Auth::user();
        Log::info('Counting distinct project_ids for approved user analyses', [
            'user_id' => $user->user_id
        ]);

        $count = Analysis::where('user_id', $user->user_id)
            ->where('status', 'approved')
            ->distinct('project_id')
            ->count('project_id');

        return response()->json([
            'status' => 200,
            'approved_count' => $count,
            'message' => 'Approved user analyses for project counted successfully'
        ], 200);
    } catch (\Exception $e) {
        Log::error('Error counting approved user analyses', [
            'error' => $e->getMessage(),
            'user_id' => Auth::id()
        ]);
        return response()->json([
            'status' => 500,
            'message' => 'Error counting approved user analyses'
        ], 500);
    }
}

/**
 * Count distinct project_ids with rejected user analyses
 */
public function countRejectedUserAnalyses()
{
    try {
        $user = Auth::user();
        Log::info('Counting distinct project_ids for rejected user analyses', [
            'user_id' => $user->user_id
        ]);

        $count = Analysis::where('user_id', $user->user_id)
            ->where('status', 'rejected')
            ->distinct('project_id')
            ->count('project_id');

        return response()->json([
            'status' => 200,
            'rejected_count' => $count,
            'message' => 'Rejected user analyses for project counted successfully'
        ], 200);
    } catch (\Exception $e) {
        Log::error('Error counting rejected user analyses', [
            'error' => $e->getMessage(),
            'user_id' => Auth::id()
        ]);
        return response()->json([
            'status' => 500,
            'message' => 'Error counting rejected user analyses'
        ], 500);
    }
}



public function updateFromExcel(Request $request)
{
    try {
        // Validate the request
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls',
            'project_id' => 'required|exists:projects,project_id',
        ]);

        $projectId = $request->input('project_id');
        $file = $request->file('excel_file');

        // Check if there are existing analyses for the project
        $existingAnalyses = Analysis::where('project_id', $projectId)->count();
        
        if ($existingAnalyses === 0) {
            return response()->json([
                'status' => 404,
                'message' => 'No existing analyses found for the provided project_id'
            ], 404);
        }

        // Delete existing analyses for the project
        Analysis::where('project_id', $projectId)->delete();

        // Import new data from Excel
        $importer = new AnalysisImport($projectId);
        Excel::import($importer, $file);

        // Count imported rows
        $rowCount = Analysis::where('project_id', $projectId)
            ->whereNotNull('serial_number')
            ->count();

        if ($rowCount === 0) {
            throw new \Exception('No meaningful data was imported from the Excel file');
        }

        // Log the update
        Log::info('Analysis data updated successfully from Excel', [
            'user_id' => Auth::id(),
            'project_id' => $projectId,
            'row_count' => $rowCount
        ]);

        // Get uploader and project details
        $uploader = Auth::user();
        $project = Project::find($projectId);
        $projectName = $project->project_name ?? 'Unknown Project';

        // Notify admins (role_id = 1)
        $adminUsers = User::where('role_id', 1)
            ->where('user_id', '!=', Auth::id())
            ->get();

        $dateTime = new DateTime('now', new DateTimeZone('Africa/Nairobi'));
        $formattedDateTime = $dateTime->format('Y-m-d H:i:s');

        $adminSubject = "Analysis Updated for Project: {$projectName}";
        $adminBody = "The analysis for project '{$projectName}' has been updated by {$uploader->name}.\n"
            . "Rows imported: {$rowCount}.\n"
            . "Updated on: {$formattedDateTime} EAT.\n"
            . "Please review the updated analysis in the portal.";

        foreach ($adminUsers as $admin) {
            try {
                Mail::raw($adminBody, function ($message) use ($admin, $adminSubject) {
                    $message->to($admin->email)
                        ->subject($adminSubject);
                });
                Log::info('Email sent to admin', ['email' => $admin->email]);
            } catch (\Exception $e) {
                Log::error('Failed to send email to admin', [
                    'email' => $admin->email,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Notify uploader
        $uploaderSubject = "Analysis Update Successful: {$projectName}";
        $uploaderBody = "Hi {$uploader->name},\n"
            . "Your analysis update for project '{$projectName}' has been successfully processed.\n"
            . "Rows imported: {$rowCount}.\n"
            . "Updated on: {$formattedDateTime} EAT.\n"
            . "The team has been notified for review.";

        try {
            Mail::raw($uploaderBody, function ($message) use ($uploader, $uploaderSubject) {
                $message->to($uploader->email)
                    ->subject($uploaderSubject);
            });
            Log::info('Email sent to uploader', ['email' => $uploader->email]);
        } catch (\Exception $e) {
            Log::error('Failed to send email to uploader', [
                'email' => $uploader->email,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Analysis data updated successfully',
            'rows_imported' => $rowCount
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'status' => 422,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);

    } catch (\Exception $e) {
        Log::error('Error updating analysis data from Excel', [
            'error' => $e->getMessage(),
            'user_id' => Auth::id(),
            'project_id' => $request->input('project_id', 'unknown')
        ]);

        return response()->json([
            'status' => 500,
            'message' => 'Error updating analysis data',
            'error' => $e->getMessage()
        ], 500);
    }
}
}