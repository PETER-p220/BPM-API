<?php

namespace App\Http\Controllers;

use App\Imports\PriceSchedulesImport;
use App\Models\PriceSchedule;
use App\Models\User; 
use App\Models\Tender;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use DateTime;
use DateTimeZone;

class PriceScheduleController extends Controller
{
    public function index()
    {
        try {
            $priceSchedules = PriceSchedule::with(['tender', 'user'])->get();
            
            return response()->json([
                'status' => 200,
                'data' => $priceSchedules,
                'message' => 'Price schedule data retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching price schedule data', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'status' => 500,
                'message' => 'Error retrieving price schedule data',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function userSchedule()
    {
        try {
            $user = Auth::user();
            // Debug: Log or return the user ID
            Log::info('Authenticated user ID: ' . $user->user_id);
            // OR temporarily return it
            // return response()->json(['user_id' => $user->id]);
    
            $priceSchedules = PriceSchedule::with(['tender', 'user'])
                ->where('user_id', $user->user_id)
                ->get();
            
            return response()->json([
                'status' => 200,
                'data' => $priceSchedules,
                'message' => 'Price schedule data retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching price schedule data', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            return response()->json([
                'status' => 500,
                'message' => 'Error retrieving price schedule data',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function store(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|file|mimes:xlsx,xls',
                'tender_id' => 'required|exists:tenders,tender_id',
            ]);

            $file = $request->file('excel_file');
            $importer = new PriceSchedulesImport($request->tender_id);
            Excel::import($importer, $file);

            $rowCount = PriceSchedule::where('tender_id', $request->tender_id)
                                    ->whereNotNull('serial_number')
                                    ->count();

            if ($rowCount === 0) {
                throw new \Exception('No meaningful data was imported from the Excel file');
            }

            Log::info('Price schedule data imported successfully', [
                'user_id' => Auth::id(),
                'tender_id' => $request->tender_id,
                'row_count' => $rowCount
            ]);

            $uploader = Auth::user();
            $uploaderName = $uploader->name;
            $uploaderEmail = $uploader->email;

            $tender = \App\Models\Tender::find($request->tender_id);
            $tenderTitle = $tender->title ?? 'Unknown Tender';

            $adminUsers = User::where('role_id', 1)
                            ->where('user_id', '!=', Auth::id())
                            ->get();

            $adminSubject = "New Price Schedule Uploaded for Review: {$tenderTitle}";
            $adminBody = "A new price schedule has been uploaded by {$uploaderName} for the tender '{$tenderTitle}'.\n"
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

            $uploaderSubject = "Your Price Schedule Upload was Successful: {$tenderTitle}";
            $uploaderBody = "Hi {$uploaderName},\n"
                          . "Your price schedule for the tender '{$tenderTitle}' has been successfully uploaded.\n"
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
                'message' => 'Price schedule data imported successfully',
                'rows_imported' => $rowCount
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error importing price schedule data', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Error importing price schedule data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($price_schedule_id)
    {
        try {
            $priceSchedule = PriceSchedule::with(['tender', 'user'])
                ->findOrFail($price_schedule_id);

            return response()->json([
                'status' => 200,
                'data' => $priceSchedule,
                'message' => 'Price schedule retrieved successfully'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Price schedule not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error fetching price schedule', [
                'price_schedule_id' => $price_schedule_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Error retrieving price schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

   
    
    public function update(Request $request)
    {
        try {
            $request->validate([
                'tender_id' => 'required|exists:tenders,tender_id',
                'status' => 'required|in:approved,rejected',
                'reason_for_reject' => 'required_if:status,rejected|nullable|string',
            ]);

            $tenderId = $request->input('tender_id');
            $status = $request->input('status');
            $reasonForReject = $request->input('reason_for_reject');

            $pendingPriceSchedules = PriceSchedule::where('tender_id', $tenderId)
                                                ->where('status', 'pending')
                                                ->get();

            if ($pendingPriceSchedules->isEmpty()) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Price schedules for that tender already approved/rejected cannot be updated again please contact administrator'
                ], 404);
            }

            $updatedCount = PriceSchedule::where('tender_id', $tenderId)
                                        ->where('status', 'pending')
                                        ->update([
                                            'status' => $status,
                                            'reason_for_reject' => $status === 'rejected' ? $reasonForReject : null,
                                            'updated_at' => now(),
                                        ]);

            Log::info('Pending price schedules updated successfully', [
                'tender_id' => $tenderId,
                'status' => $status,
                'updated_count' => $updatedCount,
                'user_id' => Auth::id()
            ]);

            $tender = Tender::find($tenderId);
            $tenderTitle = $tender->title ?? 'Unknown Tender';
            $uploaderId = $pendingPriceSchedules->first()->user_id;
            $uploader = User::find($uploaderId);

            if ($uploader) {
                $dateTime = new DateTime('now', new DateTimeZone('Africa/Nairobi'));
                $formattedDateTime = $dateTime->format('Y-m-d H:i:s');

                $subject = "Your Price Schedule has been " . ucfirst($status);
                $body = "Hi {$uploader->name},\n"
                      . "Your price schedule for title: {$tenderTitle} has been {$status} on {$formattedDateTime} EAT.\n"
                      . "Please log in to the portal for more details.";

                try {
                    Mail::raw($body, function ($message) use ($uploader, $subject) {
                        $message->to($uploader->email)
                                ->subject($subject);
                    });
                    Log::info('Email sent to uploader', [
                        'email' => $uploader->email,
                        'tender_id' => $tenderId
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send email to uploader', [
                        'email' => $uploader->email,
                        'tender_id' => $tenderId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'status' => 200,
                'message' => "Updated {$updatedCount} pending price schedules successfully",
                'data' => [
                    'tender_id' => $tenderId,
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
            Log::error('Error updating price schedules', [
                'tender_id' => $request->input('tender_id', 'unknown'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Error updating price schedules',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    

    public function destroy($price_schedule_id)
    {
        try {
            $priceSchedule = PriceSchedule::findOrFail($price_schedule_id);
            $priceSchedule->delete();

            Log::info('Price schedule deleted successfully', [
                'price_schedule_id' => $price_schedule_id,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Price schedule deleted successfully'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Price schedule not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error deleting price schedule', [
                'price_schedule_id' => $price_schedule_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Error deleting price schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    /**
 * Count distinct tender_ids for all price schedules
 */
public function countSubmittedPriceSchedules()
{
    try {
        $user = Auth::user();
        Log::info('Counting distinct tender_ids for price schedules', [
            'user_id' => $user->user_id
        ]);

        $count = PriceSchedule::distinct('tender_id')
            ->count('tender_id');

        return response()->json([
            'status' => 200,
            'total_count' => $count,
            'message' => 'Price schedules for tender counted successfully'
        ], 200);
    } catch (\Exception $e) {
        Log::error('Error counting price schedules', [
            'error' => $e->getMessage(),
            'user_id' => Auth::id()
        ]);
        return response()->json([
            'status' => 500,
            'message' => 'Error counting price schedules'
        ], 500);
    }
}

    /**
     * Count distinct tender_ids with approved (passed) price schedules
     */
    public function countApprovedPriceSchedules()
    {
        try {
            $user = Auth::user();
            Log::info('Counting distinct tender_ids for approved price schedules', [
                'user_id' => $user->user_id
            ]);

            $count = PriceSchedule::where('status', 'approved')
                ->distinct('tender_id')
                ->count('tender_id');

            return response()->json([
                'status' => 200,
                'passed_count' => $count,
                'message' => 'Approved price schedules for tender counted successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error counting approved price schedules', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            return response()->json([
                'status' => 500,
                'message' => 'Error counting approved price schedules'
            ], 500);
        }
    }

    /**
     * Count distinct tender_ids with rejected price schedules
     */
    public function countRejectedPriceSchedules()
    {
        try {
            $user = Auth::user();
            Log::info('Counting distinct tender_ids for rejected price schedules', [
                'user_id' => $user->user_id
            ]);

            $count = PriceSchedule::where('status', 'rejected')
                ->distinct('tender_id')
                ->count('tender_id');

            return response()->json([
                'status' => 200,
                'rejected_count' => $count,
                'message' => 'Rejected price schedules for tender counted successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error counting rejected price schedules', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            return response()->json([
                'status' => 500,
                'message' => 'Error counting rejected price schedules'
            ], 500);
        }
    }
}