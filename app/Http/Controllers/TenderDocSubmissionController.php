<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\TenderDocSubmission;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Tender;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Cloudinary\Cloudinary;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class TenderDocSubmissionController extends Controller
{

protected $cloudinary;

public function __construct()
{
    // Initialize Cloudinary with credentials from config
    $this->cloudinary = new Cloudinary([
        'cloud' => [
            'cloud_name' => config('cloudinary.cloud.cloud_name'),
            'api_key' => config('cloudinary.cloud.api_key'),
            'api_secret' => config('cloudinary.cloud.api_secret'),
        ],
        'url' => [
            'secure' => true,  // Ensure all URLs are secure (HTTPS)
        ],
    ]);
}


public function index()
{
    $submissions = TenderDocSubmission::with([
        'user:user_id,name',
        'tender:tender_id,tender_number,title'
    ])
    ->orderBy('submission_id', 'desc') // Order by submission_id in descending order
    ->get();

    return response()->json([
        'status' => true,
        'message' => 'Submissions fetched successfully.',
        'data' => $submissions
    ], 200);
}





public function yourSubmission()
{
    $submissions = TenderDocSubmission::with([
        'user:user_id,name',
        'tender:tender_id,tender_number,title'
    ])
    ->where('user_id', Auth::id())
    ->orderBy('submission_id', 'desc') // Order by submission_id in descending order
    ->get();

    return response()->json([
        'status' => true,
        'message' => 'Submissions fetched successfully.',
        'data' => $submissions
    ], 200);
}

public function store(Request $request)
{
    try {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'tender_id' => 'required|exists:tenders,tender_id',
            'submission_document' => 'required|mimes:pdf|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors occurred',
                'errors' => $validator->errors()
            ], 400);
        }

        // Get authenticated user ID
        $userId = auth()->id();
        if (!$userId) {
            return response()->json([
                'status' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        // Check for existing submission
        $existingSubmission = TenderDocSubmission::where([
            'tender_id' => $request->tender_id,
            'user_id' => $userId,
            'is_submitted' => 'submitted'
        ])->exists();

        if ($existingSubmission) {
            return response()->json([
                'status' => false,
                'message' => 'Tender already submitted'
            ], 400);
        }

        // Verify tender expiration
        $tender = DB::table('tenders')
            ->where('tender_id', $request->tender_id)
            ->first();

        if (!$tender) {
            return response()->json([
                'status' => false,
                'message' => 'Tender not found'
            ], 404);
        }

        if ($tender->expired_at < now()) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot submit: Tender has expired'
            ], 400);
        }

        // Handle file upload
        $submissionDocumentUrl = $this->uploadSubmissionDocument($request);
        
        // Log submission attempt (pre-log)
        Log::info('Tender submission attempt', [
            'tender_id' => $request->tender_id,
            'user_id' => $userId,
            'timestamp' => now()
        ]);

        // Create submission record
        $submission = TenderDocSubmission::create([
            'tender_id' => $request->tender_id,
            'user_id' => $userId,
            'submission_document' => $submissionDocumentUrl,
            'is_submitted' => 'submitted'
        ]);

        // Update assigned tender status if exists
        DB::table('assign_tenders')
            ->where('tender_id', $request->tender_id)
            ->update(['is_assigned' => 'submitted']);

        // Send notification
        $this->sendTenderSubmissionNotification($submission);

        // Log successful submission
        Log::info('Tender submission successful', [
            'tender_id' => $request->tender_id,
            'submission_id' => $submission->id
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Tender submitted successfully',
            'data' => $submission
        ], 201);

    } catch (\Exception $e) {
        // Log error
        Log::error('Tender Already submitted', [
            'tender_id' => $request->tender_id ?? null,
            'user_id' => auth()->id() ?? null,
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'status' => false,
            'message' => 'Submission failed due to an error',
            'error' => $e->getMessage()
        ], 500);
    }
}


private function uploadSubmissionDocument(Request $request)
{
    if ($request->hasFile('submission_document')) {
        $file = $request->file('submission_document');

        // Upload to Cloudinary
        $uploadResult = $this->cloudinary->uploadApi()->upload($file->getRealPath(), [
            'folder' => 'submission_documents',  // Store in 'submission_documents' folder
            'resource_type' => 'auto',           // Automatically detect file type (important for PDFs)
        ]);

        // Log the result as an array instead of an object
        \Log::info('Cloudinary Upload Result for Submission Document:', (array) $uploadResult);

        // Return the secure URL for the uploaded document
        return $uploadResult['secure_url'];
    }

    return null;  // If no file is uploaded, return null
}


// Send email notifications to users with role_id = 2
private function sendTenderSubmissionNotification(TenderDocSubmission $submission)
{
    // Fetch all user emails with role_id = 2 (excluding the creator)
    $userEmails = User::where('role_id', 2)->pluck('email')->toArray();

    $subject = 'New Tender Submission: ' . $submission->tender->title;
    $emailBody = "A new tender submission has been made for the tender: {$submission->tender->title} (Tender ID: {$submission->tender_id}).\n"
        . "Please log in to the portal for more details.";

    // Send email to all users with role_id = 2
    foreach ($userEmails as $email) {
        try {
            Mail::raw($emailBody, function ($message) use ($email, $subject) {
                $message->to($email);
                $message->subject($subject);
            });
        } catch (\Exception $e) {
            \Log::error("Failed to send email to {$email}: " . $e->getMessage());
        }
    }
}


   public function show($submission_id)
{
    // Retrieve the submission based on the submission_id
    $submission = TenderDocSubmission::where('submission_id', $submission_id)->first();

    // Check if the submission exists
    if (!$submission) {
        return response()->json([
            'status' => false,
            'message' => 'Submission not found.',
        ], 404);
    }

    return response()->json([
        'status' => true,
        'message' => 'Submission retrieved successfully.',
        'data' => $submission,
    ], 200);
}







    // Delete - Remove a submission
    public function destroy($id)
    {
        try {
            $submission = TenderDocSubmission::findOrFail($id);

            // Delete the file
            $file = public_path('submissions/' . $submission->submission_document);
            if (file_exists($file)) {
                unlink($file);
            }

            $submission->delete();

            return response()->json([
                'status' => true,
                'message' => 'Submission deleted successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete submission.',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function countSubmissions()
{
    $count = TenderDocSubmission::count();

    return response()->json([
        'status' => true,
        'message' => 'Total tender submissions counted successfully.',
        'submitted_tenders' => $count
    ], 200);
}



public function getSubmittedTenderReport(Request $request)
{
    try {
        // Validate request data using query parameters
        $validatedData = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'tender_type' => 'required|string',
        ]);

        // Build the query
        $query = TenderDocSubmission::with([
            'tender:tender_id,title,tender_number,expired_at',
            'user:user_id,name'
        ])->whereBetween(DB::raw("DATE(created_at)"), [$validatedData['from'], $validatedData['to']]);

        // If 'tender_type' is not "all-tenders", filter by tender type
        if ($validatedData['tender_type'] !== 'all-tenders') {
            $query->whereHas('tender', function ($q) use ($validatedData) {
                $q->where('tender_type', $validatedData['tender_type']);
            });
        }

        // Fetch the assigned tenders
        $assignedTenders = $query->orderBy('created_at', 'desc')->get();

        // If no tenders are found, return an empty response
        if ($assignedTenders->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Tender not found.',
                'error' => 'No query results for model [App\\Models\\AssignTender].'
            ], 404);
        }

        // Format the response
        $formattedAssignedTenders = $assignedTenders->map(function ($assignTender) {
            return [
                'submission_id' => $assignTender->id, // Change id to submission_id
                'tender_id' => $assignTender->tender->tender_id,
                'user_id' => $assignTender->user->user_id,
                'submission_document' => $assignTender->submission_document, // Add this field
                'is_submitted' => $assignTender->is_submitted, // Add this field
                'created_at' => $assignTender->created_at->toIso8601String(),
                'updated_at' => $assignTender->updated_at->toIso8601String(),
                'user' => [
                    'user_id' => $assignTender->user->user_id,
                    'name' => $assignTender->user->name,
                ],
                'tender' => [
                    'tender_id' => $assignTender->tender->tender_id,
                    'tender_number' => $assignTender->tender->tender_number,
                    'title' => $assignTender->tender->title,
                ]
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Submissions fetched successfully.',
            'data' => $formattedAssignedTenders
        ], 200);

    } catch (ValidationException $e) {
        return response()->json([
            'status' => false,
            'message' => 'Validation failed.',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        \Log::error('Error fetching assigned tenders report: ' . $e->getMessage());
        return response()->json([
            'status' => false,
            'message' => 'An error occurred while fetching the report.',
            'error' => $e->getMessage()
        ], 500);
    }
}




public function getAllTenderTypesForSubmittedOnes()
{
    try {
        // Fetch all unique tender types from AssignTender
        $tenderTypes = TenderDocSubmission::with('tender')->get()->pluck('tender.tender_type')->unique();

        // If no tender types are found, return an empty response
        if ($tenderTypes->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No tender types found.',
                'error' => 'No query results for tender types.'
            ], 404);
        }

        // Format the response
        $formattedTenderTypes = $tenderTypes->map(function ($type) {
            return [
                'tender_type' => $type,
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Tender types fetched successfully.',
            'data' => $formattedTenderTypes
        ], 200);

    } catch (\Exception $e) {
        \Log::error('Error fetching tender types: ' . $e->getMessage());
        return response()->json([
            'status' => false,
            'message' => 'An error occurred while fetching tender types.',
            'error' => $e->getMessage()
        ], 500);
    }
}


// Count submitted tenders for the logged-in user
public function countSubmittedTenders()
{
    $userId = auth()->id(); // Get logged-in user ID

    $submittedCount = TenderDocSubmission::where('user_id', $userId)
        ->where('is_submitted', 'submitted')
        ->count();

    return response()->json([
        'status' => true,
        'message' => 'Total submitted tenders counted successfully.',
        'submittedCount' => $submittedCount
    ], 200);
}



}

