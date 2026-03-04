<?php

namespace App\Http\Controllers;

use App\Models\AssignTender;
use App\Models\User;
use App\Models\Tender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;
use App\Models\UserLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AssignTenderController extends Controller
{

      public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['register', 'login']);
    }


    public function index()
{
    $data = AssignTender::with([
        'tender:tender_id,title,tender_type,procurement_entity,tender_number,attachment,date_of_Publication,bid_submission,expired_at', // Fetching the new fields from the 'tenders' table
        'user:user_id,name' // Only fetching the 'id' and 'name' columns from 'users'
    ])->get();

    return response()->json([
        'status' => 'success',
        'data' => $data
    ]);
}



public function yourTender()
{
    $userId = Auth::id();

    $data = AssignTender::with([
        'tender:tender_id,title,tender_type,procurement_entity,tender_number,attachment,date_of_Publication,bid_submission,expired_at', // Fetching the new fields from the 'tenders' table
        'user:user_id,name' // Only fetching the necessary columns from the 'users' table
    ])
    ->where('user_id', $userId)
    ->get();

    return response()->json([
        'status' => 'success',
        'data' => $data
    ]);
}



public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'tender_id' => [
            'required',
            'exists:tenders,tender_id',
            function ($attribute, $value, $fail) use ($request) {
                // Check if the tender is already assigned
                $existingAssignment = AssignTender::where('tender_id', $request->tender_id)
                    ->whereIn('is_assigned', ['on-progress', 'submitted'])
                    ->first();

                if ($existingAssignment) {
                    // Customize message based on the status
                    if ($existingAssignment->is_assigned === 'submitted') {
                        $fail('Tender is already submitted.');
                    } elseif ($existingAssignment->is_assigned === 'on-progress') {
                        $fail('Tender is already on progress.');
                    }
                }
            },
        ],
        'user_id' => 'required|exists:users,user_id',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'message' => 'Validation errors.',
            'errors' => $validator->errors()
        ], 400);
    }

    try {
        // Create the assignment with default 'is_assigned' set to 'on-progress'
        $assignment = AssignTender::create([
            'tender_id' => $request->tender_id,
            'user_id' => $request->user_id,
            'is_assigned' => 'on-progress', // Default value
        ]);

        // Get the user email based on user_id
        $user = User::find($request->user_id);
        if ($user) {
            $this->sendTenderAssignedEmail($user, $assignment);
        }

        return response()->json([
            'status' => true,
            'message' => 'Tender assignment created successfully.',
            'data' => $assignment
        ], 201);
    } catch (Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to create assignment.',
            'error' => $e->getMessage()
        ], 500);
    }
}


    // Send an email notification to the user about the new tender assignment
    private function sendTenderAssignedEmail(User $user, AssignTender $assignment)
    {
        // Get tender information
        $tender = Tender::find($assignment->tender_id);
        $subject = 'New Tender Assigned: ' . ($tender ? $tender->title : 'No Tender Found');

        // Prepare the email content
        $emailBody = "Dear {$user->name},\n\n"
            . "You have been assigned a new tender from the department: {$tender->department}.\n"
            . "Please check your portal for more details and to take necessary actions.\n\n"
            . "Thank you for your attention.";

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



// Show a specific assignment by assign_id
public function show($assign_id)
{
    try {
        // Use assign_id to find the record
        $assignment = AssignTender::where('assign_id', $assign_id)->firstOrFail();

        return response()->json([
            'status' => true,
            'message' => 'Assignment fetched successfully.',
            'data' => $assignment
        ], 200);
    } catch (Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to fetch assignment.',
            'error' => $e->getMessage()
        ], 500);
    }
}



    // Update an assignment by ID
   public function update(Request $request, $assign_id)
    {
        // Validate request inputs
        $validator = Validator::make($request->all(), [
            'tender_id' => 'nullable|exists:tenders,tender_id',
            'user_id' => 'nullable|exists:users,user_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors.',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            // Find assignment by assign_id
            $assignment = AssignTender::findOrFail($assign_id);

            // Update assignment data excluding 'recieved_status'
            $assignment->update([
                'tender_id' => $request->tender_id,
                'user_id' => $request->user_id,
            ]);

            return response()->json(['status' => true, 'message' => 'Tender assignment updated successfully.']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }



    // Delete an assignment by ID
    public function destroy($id)
    {
        try {
            $assignment = AssignTender::findOrFail($id);
            $assignment->delete();

            return response()->json([
                'status' => true,
                'message' => 'Tender assignment deleted successfully.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete assignment.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

public function getAssignedTenderReport(Request $request)
{
    try {
        // Validate request data
        $validatedData = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'tender_type' => 'required|string',
        ]);

        // Build the query
        $query = AssignTender::with([
            'tender:tender_id,title,tender_type,procurement_entity,tender_number,attachment,date_of_Publication,bid_submission,expired_at',
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
                'assign_tender_id' => $assignTender->id,
                'tender' => [
                    'tender_id' => $assignTender->tender->tender_id,
                    'title' => $assignTender->tender->title,
                    'tender_type' => $assignTender->tender->tender_type,
                    'procurement_entity' => $assignTender->tender->procurement_entity,
                    'tender_number' => $assignTender->tender->tender_number,
                    'attachment' => $assignTender->tender->attachment,
                    'date_of_Publication' => $assignTender->tender->date_of_Publication,
                    'expired_at' => $assignTender->tender->expired_at,
                    'bid_submission' => $assignTender->tender->bid_submission,
                ],
                'user' => [
                    'user_id' => $assignTender->user->user_id,
                    'name' => $assignTender->user->name,
                ],
                'created_at' => $assignTender->created_at->format('Y-m-d'),
                'updated_at' => $assignTender->updated_at->format('Y-m-d'),
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Assigned tenders fetched successfully.',
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



public function getAllTenderTypesForAssigned()
{
    try {
        // Fetch all unique tender types from AssignTender
        $tenderTypes = AssignTender::with('tender')->get()->pluck('tender.tender_type')->unique();

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

// Count on-progress tenders for the logged-in user
public function countOnProgressTenders()
{
    $userId = auth()->id(); // Get logged-in user ID

    $onProgressCount =  AssignTender::where('user_id', $userId)
        ->where('is_assigned', 'on-progress')
        ->count();

    return response()->json([
        'status' => true,
        'message' => 'Total on-progress tenders counted successfully.',
        'onProgressCount' => $onProgressCount
    ], 200);
}

public function countAllAssignedTenders()
{
    $assignedCount = AssignTender::count(); // Count all assigned tenders

    return response()->json([
        'status' => true,
        'message' => 'Total assigned tenders counted successfully.',
        'assignedCount' => $assignedCount
    ], 200);
}


public function countAssignedTenders()
{
    $userId = auth()->id(); // Get logged-in user ID

    $assignedCount = AssignTender::where('user_id', $userId)->count();

    return response()->json([
        'status' => true,
        'message' => 'Total assigned tenders counted successfully.',
        'assignedCount' => $assignedCount
    ], 200);
}


// Count all assigned tenders that have reached the expired_at date
public function countExipredTenders()
{
    $userId = auth()->id(); // Get logged-in user ID

    $assignedCount = AssignTender::where('user_id', $userId)
        ->whereHas('tender', function ($query) {
            $query->where('expired_at', '<=', now()) // Count if expired_at has passed
                  ->where('is_assigned', 'on-progress'); // Filter by is_assigned
        })
        ->count();

    return response()->json([
        'status' => true,
        'message' => 'Total expired tenders counted successfully.',
        'expired_tenders' => $assignedCount
    ], 200);
}

public function countAllExpiredTenders()
{
    $assignedCount = AssignTender::whereHas('tender', function ($query) {
            $query->where('expired_at', '<=', now()) // Count if expired_at has passed
                  ->where('is_assigned', 'on-progress'); // Filter by is_assigned
        })
        ->count();

    return response()->json([
        'status' => true,
        'message' => 'Total expired tenders counted successfully.',
        'expired_tenders' => $assignedCount
    ], 200);
}


// Count assigned tenders that will reach their deadline within three days
public function countDeadlineReachedTenders()
{
    $userId = auth()->id(); // Get logged-in user ID

    $assignedCount = AssignTender::where('user_id', $userId)
        ->whereHas('tender', function ($query) {
            $query->where('expired_at', '>=', now())
                  ->where('expired_at', '<=', now()->addDays(3)) // Count tenders expiring within three days
                  ->where('is_assigned', 'on-progress'); // Filter by is_submitted
        })
        ->count();

    return response()->json([
        'status' => true,
        'message' => 'Total tenders reaching the deadline within three days counted successfully.',
        'expired_tenders' => $assignedCount
    ], 200);
}


// Count assigned tenders that will reach their deadline within three days
public function countAllDeadlineReachedTenders()
{
    $assignedCount = AssignTender::whereHas('tender', function ($query) {
        $query->where('expired_at', '>=', now())
              ->where('expired_at', '<=', now()->addDays(3)) // Count tenders expiring within three days
              ->where('is_assigned', 'on-progress'); // Filter by is_submitted
    })->count();

    return response()->json([
        'status' => true,
        'message' => 'Total tenders reaching the deadline within three days counted successfully.',
        'expired_tenders' => $assignedCount
    ], 200);
}



// Count all on-progress tenders
public function countAllOnProgressTenders()
{
    $onProgressCount = AssignTender::where('is_assigned', 'on-progress')->count();

    return response()->json([
        'status' => true,
        'message' => 'Total on-progress tenders counted successfully.',
        'onProgressCount' => $onProgressCount
    ], 200);
}



// Add this new method to your AssignTenderController class
public function checkExpiringTenders()
{
    try {
        // Get current date
        $currentDate = Carbon::now();
        
        // Calculate the date 3 days from now
        $threeDaysFromNow = $currentDate->copy()->addDays(3)->startOfDay();
        
        // Fetch assigned tenders that will expire in exactly 3 days
        $expiringTenders = AssignTender::with(['tender', 'user'])
            ->whereHas('tender', function ($query) use ($threeDaysFromNow) {
                $query->whereDate('expired_at', '=', $threeDaysFromNow);
            })
            ->get();

        // Process each expiring tender
        foreach ($expiringTenders as $assignment) {
            $user = $assignment->user;
            $tender = $assignment->tender;

            if ($user && $tender) {
                $this->sendTenderExpirationReminder($user, $tender);
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Expiration checks completed successfully',
            'checked_tenders' => $expiringTenders->count()
        ]);

    } catch (Exception $e) {
        \Log::error('Tender expiration check failed: ' . $e->getMessage());
        return response()->json([
            'status' => false,
            'message' => 'Failed to check expiring tenders',
            'error' => $e->getMessage()
        ], 500);
    }
}

// Add this helper method to send the expiration reminder email
private function sendTenderExpirationReminder(User $user, Tender $tender)
{
    $subject = "Tender Expiration Reminder: {$tender->title}";
    
    $emailBody = "Hello {$user->name},\n\n"
        . "This is a reminder that your assigned tender '{$tender->title}' "
        . "will expire in 3 days on {$tender->expired_at->format('Y-m-d')}.\n"
        . "Please ensure all necessary actions are completed before the deadline.\n\n"
        . "Thank you,\n"
        . "Tender Management System";

    try {
        Mail::raw($emailBody, function ($message) use ($user, $subject) {
            $message->to($user->email)
                    ->subject($subject);
        });
        
        // Optional: Log the successful email sending
        \Log::info("Expiration reminder sent to {$user->email} for tender {$tender->tender_id}");
        
    } catch (Exception $e) {
        \Log::error("Failed to send expiration reminder to {$user->email}: " . $e->getMessage());
    }
}
}



