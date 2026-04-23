<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Receipt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Cloudinary\Cloudinary;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReceiptController extends Controller
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

    /**
     * Store a newly submitted receipt.
     */
    public function store(Request $request)
    {
        try {
            // Validate the incoming request
            $validator = Validator::make($request->all(), [
                'receipt_file' => 'required|image|mimes:jpg,jpeg,png,gif|max:10240', // Allow only image files
                'description' => 'required|string|max:255',
                'accountant_id' => 'required|exists:users,user_id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Upload the receipt image to Cloudinary
            $file = $request->file('receipt_file');
            $uploadResult = $this->cloudinary->uploadApi()->upload($file->getRealPath(), [
                'folder' => 'receipts',
                'resource_type' => 'image',
            ]);
            $receiptUrl = $uploadResult['secure_url'];

            // Store the receipt
            $receipt = Receipt::create([
                'receipt_file' => $receiptUrl,
                'description' => $request->description,
                'user_id' => Auth::id(),  // Logged-in user submitting the receipt
                'accountant_id' => $request->accountant_id,
                'is_approved' => 'pending',
            ]);

            // Send email notification to the accountant
            $this->sendReceiptEmail($receipt);

            return response()->json([
                'status' => true,
                'message' => 'Receipt submitted successfully and sent for review.',
                'data' => $receipt
            ], 201);
        } catch (Exception $e) {
            Log::error('Failed to submit receipt: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to submit receipt.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send email notification to the accountant.
     */
    private function sendReceiptEmail(Receipt $receipt)
    {
        try {
            $accountant = User::find($receipt->accountant_id);
            $subject = 'New Receipt Submitted for Review';
            $body = "Dear {$accountant->name},\n\n"
                . "A new receipt has been submitted for your review:\n\n"
                . "Description: {$receipt->description}\n"
                . "Receipt File: {$receipt->receipt_file}\n\n"
                . "Please review the receipt and approve it accordingly.\n\n"
                . "Best regards,\n"
                . config('app.name');

            Mail::raw($body, function ($message) use ($accountant, $subject) {
                $message->to($accountant->email)
                    ->subject($subject);
            });

            Log::info('Receipt notification email sent to ' . $accountant->email);
        } catch (Exception $e) {
            Log::error('Failed to send receipt notification email: ' . $e->getMessage());
        }
    }

    /**
     * Display all receipts, including user name.
     * Also includes financial records that have a receipt file attached.
     */
    public function index()
    {
        try {
            $receipts = Receipt::join('users', 'receipts.user_id', '=', 'users.user_id')
                                ->select('receipts.*', 'users.name as user_name')
                                ->get()
                                ->map(function ($r) { $r->source = 'receipt'; return $r; });

            $financialReceipts = DB::table('financial_records')
                ->join('users', 'financial_records.created_by', '=', 'users.user_id')
                ->whereNotNull('financial_records.receipt_file')
                ->whereNull('financial_records.deleted_at')
                ->select(
                    DB::raw('financial_records.id as receipt_id'),
                    DB::raw('financial_records.created_by as user_id'),
                    DB::raw('users.name as user_name'),
                    DB::raw("COALESCE(financial_records.description, CONCAT(financial_records.type, ' - ', financial_records.category)) as description"),
                    DB::raw('financial_records.receipt_file'),
                    DB::raw('financial_records.created_at'),
                    DB::raw("'financial_record' as source")
                )
                ->get();

            $allReceipts = $receipts->toBase()->merge($financialReceipts);

            return response()->json([
                'status' => true,
                'message' => 'Receipts fetched successfully.',
                'data' => $allReceipts
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch receipts.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch all receipts for the logged-in user.
     * Also includes the user's financial records that have a receipt file attached.
     */
    public function userReceipts()
    {
        try {
            $userId = Auth::user()->user_id;

            $userReceipts = Receipt::join('users', 'receipts.user_id', '=', 'users.user_id')
                                    ->where('receipts.user_id', $userId)
                                    ->select('receipts.*', 'users.name as user_name')
                                    ->get()
                                    ->map(function ($r) { $r->source = 'receipt'; return $r; });

            $financialReceipts = DB::table('financial_records')
                ->join('users', 'financial_records.created_by', '=', 'users.user_id')
                ->where('financial_records.created_by', $userId)
                ->whereNotNull('financial_records.receipt_file')
                ->whereNull('financial_records.deleted_at')
                ->select(
                    DB::raw('financial_records.id as receipt_id'),
                    DB::raw('financial_records.created_by as user_id'),
                    DB::raw('users.name as user_name'),
                    DB::raw("COALESCE(financial_records.description, CONCAT(financial_records.type, ' - ', financial_records.category)) as description"),
                    DB::raw('financial_records.receipt_file'),
                    DB::raw('financial_records.created_at'),
                    DB::raw("'financial_record' as source")
                )
                ->get();

            $allReceipts = $userReceipts->toBase()->merge($financialReceipts);

            return response()->json([
                'status' => true,
                'message' => 'User receipts fetched successfully.',
                'data' => $allReceipts
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch user receipts.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    

    /**
     * Display the specified receipt.
     */
    public function show($receiptId)
    {
        try {
            $receipt = Receipt::join('users', 'receipts.user_id', '=', 'users.user_id')
                            ->where('receipts.receipt_id', $receiptId)
                            ->select('receipts.*', 'users.name as user_name')
                            ->firstOrFail();

            return response()->json([
                'status' => true,
                'message' => 'Receipt fetched successfully.',
                'data' => $receipt
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Receipt not found.',
                'error' => $e->getMessage()
            ], 404);
        }
    }
    
    /**
     * Update the receipt's approval status.
     */
    public function update(Request $request, $receiptId)
    {
        try {
            $receipt = Receipt::findOrFail($receiptId);

            // Validate the approval status
            $validatedData = $request->validate([
                'is_approved' => 'required|in:pending,approved',
            ]);

            // Update the approval status
            $receipt->update($validatedData);

            return response()->json([
                'status' => true,
                'message' => 'Receipt status updated successfully.',
                'data' => $receipt
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update receipt status.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified receipt.
     */
    public function destroy($receiptId)
    {
        try {
            $receipt = Receipt::findOrFail($receiptId);

            // Delete the receipt from Cloudinary and database
            $this->cloudinary->uploadApi()->destroy($receipt->receipt_file);

            $receipt->delete();

            return response()->json([
                'status' => true,
                'message' => 'Receipt deleted successfully.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete receipt.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
 * Count all receipts.
 */
public function countAllReceipts()
{
    try {
        // Count the total number of receipts
        $totalReceipts = Receipt::count();

        return response()->json([
            'status' => true,
            'message' => 'Total receipts counted successfully.',
            'data' => [
                'total_receipts' => $totalReceipts
            ]
        ], 200);
    } catch (Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Error counting total receipts.',
            'error' => $e->getMessage(),
        ], 500);
    }
}





public function getReceiptsReports(Request $request)
{
    try {
        // Validate request data using query parameters
        $validatedData = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        // Build the query to fetch receipts
        $query = Receipt::with(['user:user_id,name'])
            ->whereBetween(DB::raw("DATE(created_at)"), [$validatedData['from'], $validatedData['to']]);

        // Fetch the receipts
        $receipts = $query->orderBy('created_at', 'desc')->get([
            'receipt_id',
            'description',
            'receipt_file',
            'created_at',
            'user_id'
        ]);

        // If no receipts are found, return an empty response
        if ($receipts->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No receipts found.',
                'error' => 'No query results for model [App\\Models\\Receipt].'
            ], 404);
        }

        // Format the response
        $formattedReceipts = $receipts->map(function ($receipt) {
            return [
                'receipt_id' => $receipt->receipt_id,
                'description' => $receipt->description,
                'receipt_file' => $receipt->receipt_file,
                'created_at' => Carbon::parse($receipt->created_at)->toIso8601String(),
                'user' => [
                    'user_id' => $receipt->user->user_id,
                    'name' => $receipt->user->name,
                ]
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Receipts fetched successfully.',
            'data' => $formattedReceipts
        ], 200);

    } catch (ValidationException $e) {
        return response()->json([
            'status' => false,
            'message' => 'Validation failed.',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        Log::error('Error fetching receipts report: ' . $e->getMessage());
        return response()->json([
            'status' => false,
            'message' => 'An error occurred while fetching the report.',
            'error' => $e->getMessage()
        ], 500);
    }
}
}
