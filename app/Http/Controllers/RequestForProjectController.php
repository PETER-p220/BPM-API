<?php

namespace App\Http\Controllers;

use App\Models\RequestForProject;
use App\Models\User;
use App\Models\Tender;
use App\Models\ProjectAnalysis;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class RequestForProjectController extends Controller
{
    // Fetch all requests
    public function index()
    {
        try {
            $requests = RequestForProject::with([
                'user:user_id,name',          
                'tender:tender_id,title' 
            ])->orderBy('request_id', 'desc')->get();

            return response()->json([
                'status' => true,
                'data' => $requests,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch requests.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function ApprovedRequest()
{
    try {
        $requests = RequestForProject::with([
            'user:user_id,name',          
            'tender:tender_id,title' 
        ])
        ->where('is_approved', 'approved') // Filter for approved requests
        ->orderBy('request_id', 'desc')
        ->get();

        return response()->json([
            'status' => true,
            'data' => $requests,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to fetch requests.',
            'error' => $e->getMessage(),
        ], 500);
    }
}


    public function yourRequest()
{
    try {
        $yourRequest = RequestForProject::with([
            'user:user_id,name',          
            'tender:tender_id,title' 
        ])
        ->where('user_id', Auth::id()) 
        ->orderBy('request_id', 'desc')
        ->get();

        return response()->json([
            'status' => true,
            'data' => $yourRequest,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to fetch requests.',
            'error' => $e->getMessage(),
        ], 500);
    }
}


// Store a new request
public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'item' => 'required|array',
        'item.*' => 'required|string',
        'amount_requested' => 'nullable|numeric|min:0',
        'tender_id' => 'nullable|exists:tenders,tender_id',
        'vender' => 'nullable|string',
        'vendor_account_number' => 'nullable|string',
        'vender_account_name' => 'nullable|string',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'errors' => $validator->errors(),
        ], 422);
    }

    try {
        // Check if the user is authenticated
        $userId = Auth::id();

        if (is_null($userId)) {
            return response()->json([
                'status' => false,
                'message' => 'User is not authenticated.',
            ], 401);
        }

        // Check if tender_id is provided and fetch amount_required_for_request and tender title
        $projectAnalysis = ProjectAnalysis::where('tender_id', $request->tender_id)->first();

        if (!$projectAnalysis) {
            return response()->json([
                'status' => false,
                'message' => 'Tender analysis not found.',
            ], 404);
        }

        $amountRequiredForRequest = $projectAnalysis->amount_required_for_request;
        $tender = Tender::find($request->tender_id); // Fetch the tender to get the title

        // Validate if amount_requested is greater than amount_required_for_request
        if ($request->amount_requested > $amountRequiredForRequest) {
            return response()->json([
                'status' => false,
                'message' => 'Not enough money. The available amount is ' . $amountRequiredForRequest . '.',
            ], 422);
        }

        // Deduct the requested amount
        $projectAnalysis->amount_required_for_request -= $request->amount_requested;
        $projectAnalysis->save();

        // Create the request
        $requestData = RequestForProject::create([
            'user_id' => $userId,
            'item' => json_encode($request->item),
            'amount_requested' => $request->amount_requested,
            'tender_id' => $request->tender_id,
            'is_approved' => 'pending',
            'vender' => $request->vender,
            'vendor_account_number' => $request->vendor_account_number,
            'vender_account_name' => $request->vender_account_name,
            'created_at' => now()->setTimezone('Africa/Nairobi'), // Set created_at to EAT
        ]);

        // Fetch users with role_id = 4
        $usersToNotify = User::where('role_id', 4)->get();

        // Send notifications to users with role_id = 4
        foreach ($usersToNotify as $accountant) {
            $this->sendRequestForReviewEmail($accountant, $requestData, Auth::user()->name, $tender->title);
        }

        return response()->json([
            'status' => true,
            'message' => 'Request created successfully. Remaining balance: ' . $projectAnalysis->amount_required_for_request,
        ], 201);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to create request.',
            'error' => $e->getMessage()
        ], 500);
    }
}

// Send an email notification to the accountant for request review
private function sendRequestForReviewEmail(User $accountant, RequestForProject $requestData, $senderName, $tenderTitle)
{
    // Format the created_at timestamp to a readable format (EAT timezone)
    $createdAt = $requestData->created_at->setTimezone('Africa/Nairobi')->format('Y-m-d H:i:s');

    // Prepare the email content
    $emailBody = "Dear {$accountant->name},\n\n"
        . "A new request has been posted for review by {$senderName}:\n"
        . "Tender Title: {$tenderTitle}\n" // Include the tender title
        . "Items: {$requestData->item}\n"
        . "Amount Requested: {$requestData->amount_requested}\n"
        . "Request Created At: {$createdAt}\n\n"
        . "Please review and take necessary action.\n\n"
        . "Thank you.";

    // Send the email and handle failure
    try {
        Mail::raw($emailBody, function ($message) use ($accountant) {
            $message->to($accountant->email);
            $message->subject('New Request for Review');
        });

        return true;  // Return true if email is sent successfully
    } catch (\Exception $e) {
        \Log::error('Email sending failed: ' . $e->getMessage());
        return false;  // Return false if email sending fails
    }
}


    

    // Show a specific request
    public function show($request_id)
    {
        try {
            $request = RequestForProject::with([
                'user:user_id,name',          
                'tender:tender_id,title',
            ])->findOrFail($request_id);

            return response()->json([
                'status' => true,
                'data' => $request,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch the request.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Update a request
    public function update(Request $request, $request_id)
    {
        $validator = Validator::make($request->all(), [
            'is_approved' => 'nullable|string', // Optionally approve or reject
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }
    
        try {
            $requestForProject = RequestForProject::findOrFail($request_id);
    
            // Update the fields if provided
            $requestForProject->update([
                'is_approved' => $request->is_approved ?? $requestForProject->is_approved,
            ]);
    
            // Check if the request status is approved
            if ($request->is_approved) {
                $this->sendApprovalOrRejectionEmail($requestForProject);
                $this->notifyUsersOfApproval($requestForProject);
            }
    
            return response()->json([
                'status' => true,
                'message' => 'Request updated successfully.',
                'data' => $requestForProject,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update request.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    // Send email notification about approval or rejection
    private function sendApprovalOrRejectionEmail(RequestForProject $requestForProject)
    {
        $user = User::find($requestForProject->user_id);
        
        // Fetch the tender title associated with the request
        $tender = Tender::find($requestForProject->tender_id);
        $tenderTitle = $tender ? $tender->title : 'Unknown Tender';
    
        if ($user) {
            $subject = $requestForProject->is_approved == 'approved' ? 'Your Request has been Approved' : 'Your Request has been Rejected';
            $emailBody = "Dear {$user->name},\n\n"
                . "Your request for the project related to the tender titled '{$tenderTitle}' has been "
                . strtolower($requestForProject->is_approved) . ".\n\n"
                . "Description: {$requestForProject->item}\n"
                . "Amount Requested: {$requestForProject->amount_requested}\n\n"
                . "Thank you for your request.\n\n"
                . "Best Regards.";
    
            // Send the email
            try {
                Mail::raw($emailBody, function ($message) use ($user, $subject) {
                    $message->to($user->email);
                    $message->subject($subject);
                });
            } catch (\Exception $e) {
                \Log::error('Email sending failed: ' . $e->getMessage());
            }
        }
    }
    
    // Notify users with role_id = 1 about the approval
    private function notifyUsersOfApproval(RequestForProject $requestForProject)
    {
        $users = User::where('role_id', 1)->get();
        $tender = Tender::find($requestForProject->tender_id);
        $tenderTitle = $tender ? $tender->title : 'Unknown Tender';
        
        foreach ($users as $user) {
            $subject = 'Review New Project Request Approve';
            $emailBody = "Dear {$user->name},\n\n"
                . "A new request for the tender titled '{$tenderTitle}' has been approved and logged for your review.\n\n"
                . "Description: {$requestForProject->item}\n"
                . "Amount Requested: {$requestForProject->amount_requested}\n\n"
                . "Thank you for your attention.\n\n"
                . "Best Regards.";
    
            // Send the email
            try {
                Mail::raw($emailBody, function ($message) use ($user, $subject) {
                    $message->to($user->email);
                    $message->subject($subject);
                });
            } catch (\Exception $e) {
                \Log::error('Email sending failed: ' . $e->getMessage());
            }
        }
    }
    
    
    // Delete a request
    public function destroy($request_id)
    {
        try {
            $requestForProject = RequestForProject::findOrFail($request_id);
            $requestForProject->delete();

            return response()->json([
                'status' => true,
                'message' => 'Request deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete request.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Example of fetching a report (similar to previous fetchMeetingMinutesReport method)
    public function fetchRequestReport(Request $request)
    {
        $validatedData = $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);
    
        try {
            $date = Carbon::parse($validatedData['date'])->startOfDay();
    
            // Retrieve requests created on the given date
            $requests = RequestForProject::whereDate('created_at', $date)
                ->with(['user', 'department', 'project']) // Include related data
                ->get();
    
            if ($requests->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No requests found for the selected date.',
                ], 200);
            }
    
            return response()->json([
                'status' => 'success',
                'data' => $requests,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to fetch request report: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch the request report.',
            ], 500);
        }
    }


    // Count all requests
public function countRequests()
{
    try {
        // Count the total number of requests
        $totalRequests = RequestForProject::count();

        return response()->json([
            'status' => true,
            'totalRequests' => $totalRequests,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to count requests.',
            'error' => $e->getMessage(),
        ], 500);
    }
}



// Count total requests
public function countALlTotalRequests()
{
    try {
        // Count the total number of requests
        $totalRequests = RequestForProject::count();

        return response()->json([
            'status' => true,
            'totalRequests' => $totalRequests,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to count total requests.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

// Count approved requests
public function countALlApprovedRequests()
{
    try {
        // Count the total number of approved requests
        $approvedRequests = RequestForProject::where('is_approved', 'approved')->count();

        return response()->json([
            'status' => true,
            'approvedRequests' => $approvedRequests,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to count approved requests.',
            'error' => $e->getMessage(),
        ], 500);
    }
}


// Count rejected requests
public function countAllRejectedRequests()
{
    try {
        // Count the total number of rejected requests
        $rejectedRequests = RequestForProject::where('is_approved', 'rejected')->count();

        return response()->json([
            'status' => true,
            'rejectedRequests' => $rejectedRequests,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to count rejected requests.',
            'error' => $e->getMessage(),
        ], 500);
    }
}








// Count total requests for the logged-in user
public function countUserRequests()
{
    try {
        // Get the logged-in user's ID
        $userId = Auth::id();

        // Count the total number of requests for the logged-in user
        $totalRequests = RequestForProject::where('user_id', $userId)->count();

        return response()->json([
            'status' => true,
            'totalRequests' => $totalRequests,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to count requests.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

public function countHodRequests()
{
    try {
        $userId = Auth::id();

        if (!$userId) {
            return response()->json([
                'status' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        $count = RequestForProject::where('user_id', $userId)->count();

        return response()->json([
            'status' => true,
            'count' => $count,
        ], 200);
    } catch (\Exception $e) {
        Log::error('Error counting HOD requests: ' . $e->getMessage());

        return response()->json([
            'status' => false,
            'message' => 'Failed to count HOD requests.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

// Count approved requests for the logged-in user
public function countApprovedRequests()
{
    try {
        // Get the logged-in user's ID
        $userId = Auth::id();

        // Count the total number of approved requests for the logged-in user
        $approvedRequests = RequestForProject::where('user_id', $userId)
            ->where('is_approved', 'approved')
            ->count();

        return response()->json([
            'status' => true,
            'approvedRequests' => $approvedRequests,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to count approved requests.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

// Count rejected requests for the logged-in user
public function countRejectedRequests()
{
    try {
        // Get the logged-in user's ID
        $userId = Auth::id();

        // Count the total number of rejected requests for the logged-in user
        $rejectedRequests = RequestForProject::where('user_id', $userId)
            ->where('is_approved', 'rejected')
            ->count();

        return response()->json([
            'status' => true,
            'rejectedRequests' => $rejectedRequests,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to count rejected requests.',
            'error' => $e->getMessage(),
        ], 500);
    }
}




// Count total requests without filtering by user
public function countAllRequests()
{
    try {
        // Count the total number of requests (all users)
        $totalRequests = RequestForProject::count();

        return response()->json([
            'status' => true,
            'totalRequests' => $totalRequests,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to count requests.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

// Count approved requests without filtering by user
public function countPassedRequests()
{
    try {
        // Count the total number of approved requests (all users)
        $approvedRequests = RequestForProject::where('is_approved', 'approved')->count();

        return response()->json([
            'status' => true,
            'approvedRequests' => $approvedRequests,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to count approved requests.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

// Count rejected requests without filtering by user
public function countAllRequestsRejected()
{
    try {
        // Count the total number of rejected requests (all users)
        $rejectedRequests = RequestForProject::where('is_approved', 'rejected')->count();

        return response()->json([
            'status' => true,
            'rejectedRequests' => $rejectedRequests,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to count rejected requests.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

}



