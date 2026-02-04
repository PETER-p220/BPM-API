<?php

namespace App\Http\Controllers;

use App\Models\RequestForPurchase;
use App\Models\Analysis;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Exception;

class RequestForPurchaseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // List all requests for purchase
    public function index()
    {
        $requests = RequestForPurchase::with([
            'project:project_id,project_name',
            'analysis:analysis_id,item_description',
            'user:user_id,name'
        ])->orderBy('request_for_id', 'desc')->get();

        return response()->json([
            'status' => true,
            'message' => 'Requests for purchase fetched successfully.',
            'data' => $requests
        ], 200);
    }

    // List all requests for purchase for the logged-in user
    public function LoggedUserRequests(Request $request)
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }

            $requests = RequestForPurchase::with([
                'project:project_id,project_name',
                'analysis:analysis_id,item_description',
                'user:user_id,name'
            ])
            ->where('user_id', $userId)
            ->orderBy('request_for_id', 'desc')
            ->get();

            if ($requests->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'message' => 'No requests for purchase found for the logged-in user.',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'status' => true,
                'message' => 'Your requests for purchase fetched successfully.',
                'data' => $requests
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch requests for purchase.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,project_id',
            'analysis_id' => 'required|exists:analyses,analysis_id',
            'quantity_purchased' => 'required|integer|min:1',
            'amount_purchased' => 'required|numeric|min:0',
            'VendorName' => 'required|string|max:255',
            'VendorAccountNumber' => 'required|string|max:100',
            'VendorContact' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors.',
                'errors' => $validator->errors()
            ], 400);
        }

        $response = [
            'request_for_purchase' => ['status' => false, 'message' => '', 'data' => null],
            'email' => ['status' => false, 'message' => '', 'details' => []],
        ];

        try {
            $analysis = Analysis::findOrFail($request->analysis_id);

            // Check quantity
            if ($analysis->quantity < $request->quantity_purchased) {
                throw new Exception("Requested quantity_purchased ({$request->quantity_purchased}) exceeds available quantity ({$analysis->quantity}).");
            }

            // Check amount
            if ($analysis->amount < $request->amount_purchased) {
                throw new Exception("Requested amount_purchased ({$request->amount_purchased}) exceeds available amount ({$analysis->amount}).");
            }

            // Create the request
            $requestForPurchase = RequestForPurchase::create([
                'project_id' => $request->project_id,
                'analysis_id' => $request->analysis_id,
                'user_id' => Auth::id(),
                'status' => 'pending',
                'quantity_purchased' => $request->quantity_purchased,
                'amount_purchased' => $request->amount_purchased,
                'VendorName' => $request->VendorName,
                'VendorAccountNumber' => $request->VendorAccountNumber,
                'VendorContact' => $request->VendorContact,
            ]);

            // Update analysis table
            $newQuantity = $analysis->quantity - $request->quantity_purchased;
            $newAmount = $analysis->amount - $request->amount_purchased;
            $analysis->update([
                'quantity' => $newQuantity,
                'amount' => $newAmount,
            ]);

            $response['request_for_purchase'] = [
                'status' => true,
                'message' => 'Request for purchase created successfully.',
                'data' => [
                    'request' => $requestForPurchase->load(['project', 'analysis', 'user']),
                    'current_quantity' => $newQuantity,
                    'quantity_purchased' => $request->quantity_purchased,
                    'current_amount' => $newAmount,
                    'amount_purchased' => $request->amount_purchased,
                ],
            ];

            // Notify creator and admin (role_id = 1)
            $loggedInUser = Auth::user();
            $admin = User::where('role_id', 1)->first();
            $emailResults = [];

            // Notify the creator
            $emailResultCreator = $this->sendCreatorNotification($loggedInUser, $requestForPurchase, $newQuantity, $newAmount);
            $emailResults[] = [
                'email' => $loggedInUser->email,
                'status' => $emailResultCreator['status'],
                'message' => $emailResultCreator['message'],
            ];

            // Notify the admin
            if ($admin && $admin->user_id !== Auth::id()) {
                $emailResultAdmin = $this->sendAdminNotification($admin, $requestForPurchase, $newQuantity, $newAmount);
                $emailResults[] = [
                    'email' => $admin->email,
                    'status' => $emailResultAdmin['status'],
                    'message' => $emailResultAdmin['message'],
                ];
            } else if (!$admin) {
                $emailResults[] = [
                    'email' => null,
                    'status' => false,
                    'message' => 'No admin with role_id = 1 found.',
                ];
            }

            $allEmailsSent = !in_array(false, array_column($emailResults, 'status'));
            $response['email'] = [
                'status' => $allEmailsSent,
                'message' => $allEmailsSent ? 'All notifications sent successfully.' : 'Some notifications failed.',
                'details' => $emailResults,
            ];

            return response()->json([
                'status' => $response['request_for_purchase']['status'],
                'message' => 'Request for purchase creation and email notifications processed.',
                'results' => $response
            ], $response['request_for_purchase']['status'] ? 201 : 500);

        } catch (Exception $e) {
            $response['request_for_purchase'] = [
                'status' => false,
                'message' => 'Failed to create request for purchase.',
                'error' => $e->getMessage()
            ];

            return response()->json([
                'status' => false,
                'message' => 'Request for purchase creation failed.',
                'results' => $response
            ], 500);
        }
    }

    // Send admin notification
    private function sendAdminNotification(User $admin, RequestForPurchase $requestForPurchase, $newQuantity, $newAmount)
    {
        $project = $requestForPurchase->project;
        $analysis = $requestForPurchase->analysis;
        $itemDescription = $analysis ? ($analysis->item_description ?: 'N/A') : 'N/A';
        // Fetch the user who created the request
        $requestor = User::find($requestForPurchase->user_id);
        $requestorName = $requestor ? $requestor->name : 'Unknown User';

        $subject = "New Purchase Request Submitted";
        $emailBody = "Dear {$admin->name},\n\n"
            . "A new purchase request has been submitted for project '{$project->project_name}' by {$requestorName}:\n"
            . "Item: {$itemDescription}\n"
            . "Quantity Purchased: {$requestForPurchase->quantity_purchased}\n"
            . "Amount Purchased: {$requestForPurchase->amount_purchased}\n"
            . "Vendor Name: {$requestForPurchase->VendorName}\n"
            . "Vendor Account Number: {$requestForPurchase->VendorAccountNumber}\n"
            . "Vendor Contact: {$requestForPurchase->VendorContact}\n"
            . "Remaining Quantity: {$newQuantity}\n"
            . "Remaining Amount: {$newAmount}\n\n"
            . "Please review the request.\n\n"
            . "Thank you,\nTender Management System";

        try {
            Mail::raw($emailBody, function ($message) use ($admin, $subject) {
                $message->to($admin->email)->subject($subject);
            });
            \Log::info("Admin notification sent successfully to {$admin->email} for request_for_id: {$requestForPurchase->request_for_id}");
            return [
                'status' => true,
                'message' => "Notification sent to {$admin->email}"
            ];
        } catch (Exception $e) {
            \Log::error("Failed to send admin notification to {$admin->email} for request_for_id: {$requestForPurchase->request_for_id}: " . $e->getMessage());
            return [
                'status' => false,
                'message' => "Failed to send notification to {$admin->email}: " . $e->getMessage()
            ];
        }
    }

    // Send creator notification
    private function sendCreatorNotification(User $creator, RequestForPurchase $requestForPurchase, $newQuantity, $newAmount)
    {
        $project = $requestForPurchase->project;
        $analysis = $requestForPurchase->analysis;
        $itemDescription = $analysis ? ($analysis->item_description ?: 'N/A') : 'N/A';

        $subject = "Your Purchase Request Submission";
        $emailBody = "Dear {$creator->name},\n\n"
            . "Your purchase request for project '{$project->project_name}' has been submitted:\n"
            . "Item: {$itemDescription}\n"
            . "Quantity Purchased: {$requestForPurchase->quantity_purchased}\n"
            . "Amount Purchased: {$requestForPurchase->amount_purchased}\n"
            . "Vendor Name: {$requestForPurchase->VendorName}\n"
            . "Vendor Account Number: {$requestForPurchase->VendorAccountNumber}\n"
            . "Vendor Contact: {$requestForPurchase->VendorContact}\n"
            . "Remaining Quantity: {$newQuantity}\n"
            . "Remaining Amount: {$newAmount}\n\n"
            . "Thank you,\nTender Management System";

        try {
            Mail::raw($emailBody, function ($message) use ($creator, $subject) {
                $message->to($creator->email)->subject($subject);
            });
            \Log::info("Creator notification sent successfully to {$creator->email} for request_for_id: {$requestForPurchase->request_for_id}");
            return [
                'status' => true,
                'message' => "Notification sent to {$creator->email}"
            ];
        } catch (Exception $e) {
            \Log::error("Failed to send creator notification to {$creator->email} for request_for_id: {$requestForPurchase->request_for_id}: " . $e->getMessage());
            return [
                'status' => false,
                'message' => "Failed to send notification to {$creator->email}: " . $e->getMessage()
            ];
        }
    }

    // Show a specific request for purchase
    public function show($request_for_id)
    {
        try {
            $requestForPurchase = RequestForPurchase::with([
                'project:project_id,project_name',
                'analysis:analysis_id,item_description,quantity,amount',
                'user:user_id,name'
            ])->findOrFail($request_for_id);

            return response()->json([
                'status' => true,
                'message' => 'Request for purchase fetched successfully.',
                'data' => $requestForPurchase
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Request for purchase not found.',
                'error' => $e->getMessage()
            ], 404);
        }
    }


    public function update(Request $request)
{
    $validator = Validator::make($request->all(), [
        'request_for_id' => 'required|exists:request_for_purchases,request_for_id',
        'project_id' => 'sometimes|exists:projects,project_id',
        'analysis_id' => 'sometimes|exists:analyses,analysis_id',
        'quantity_purchased' => 'sometimes|integer|min:1',
        'amount_purchased' => 'sometimes|numeric|min:0',
        'VendorName' => 'sometimes|string|max:255',
        'VendorAccountNumber' => 'sometimes|string|max:100',
        'VendorContact' => 'sometimes|string|max:100',
        'status' => 'sometimes|in:pending,accepted,rejected',
        'rejection_reason' => 'nullable|string|max:500|required_if:status,rejected',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'message' => 'Validation errors.',
            'errors' => $validator->errors()
        ], 400);
    }

    try {
        $requestForPurchase = RequestForPurchase::findOrFail($request->request_for_id);
        $data = [];

        // Fields to update
        $fields = [
            'project_id', 'analysis_id', 'quantity_purchased', 'amount_purchased',
            'VendorName', 'VendorAccountNumber', 'VendorContact', 'status', 'rejection_reason'
        ];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->$field;
            }
        }

        // Track if status is changing to accepted or rejected
        $statusChanged = isset($data['status']) && $data['status'] !== $requestForPurchase->status && in_array($data['status'], ['accepted', 'rejected']);

        // Handle analysis updates if quantity or amount changes
        $newQuantity = null;
        $newAmount = null;
        if (isset($data['quantity_purchased']) || isset($data['amount_purchased'])) {
            $analysis = Analysis::findOrFail($request->analysis_id ?? $requestForPurchase->analysis_id);

            // Revert previous deduction
            $analysis->quantity += $requestForPurchase->quantity_purchased;
            $analysis->amount += $requestForPurchase->amount_purchased;

            // Check new values
            $newQuantityPurchased = $data['quantity_purchased'] ?? $requestForPurchase->quantity_purchased;
            $newAmountPurchased = $data['amount_purchased'] ?? $requestForPurchase->amount_purchased;

            if ($analysis->quantity < $newQuantityPurchased) {
                throw new Exception("Requested quantity_purchased ({$newQuantityPurchased}) exceeds available quantity ({$analysis->quantity}).");
            }
            if ($analysis->amount < $newAmountPurchased) {
                throw new Exception("Requested amount_purchased ({$newAmountPurchased}) exceeds available amount ({$analysis->amount}).");
            }

            // Apply new deduction
            $newQuantity = $analysis->quantity - $newQuantityPurchased;
            $newAmount = $analysis->amount - $newAmountPurchased;
            $analysis->update([
                'quantity' => $newQuantity,
                'amount' => $newAmount,
            ]);
            $data['current_quantity'] = $newQuantity;
            $data['current_amount'] = $newAmount;
        }

        // Clear rejection_reason if status is not rejected
        if (isset($data['status']) && $data['status'] !== 'rejected') {
            $data['rejection_reason'] = null;
        }

        $requestForPurchase->update($data);

        // Send notification if status changed to accepted or rejected
        if ($statusChanged) {
            $creator = User::findOrFail($requestForPurchase->user_id);
            $notificationResult = $this->sendApproveNotification($creator, $requestForPurchase);
            if (!$notificationResult['status']) {
                \Log::warning("Notification failure: " . $notificationResult['message']);
                // Continue execution even if notification fails
            }
        }

        $responseData = [
            'request' => $requestForPurchase->load(['project', 'analysis', 'user']),
            'current_quantity' => $newQuantity ?? $analysis->quantity ?? null,
            'quantity_purchased' => $data['quantity_purchased'] ?? $requestForPurchase->quantity_purchased,
            'current_amount' => $newAmount ?? $analysis->amount ?? null,
            'amount_purchased' => $data['amount_purchased'] ?? $requestForPurchase->amount_purchased,
        ];

        return response()->json([
            'status' => true,
            'message' => 'Request for purchase updated successfully.',
            'data' => $responseData
        ], 200);

    } catch (Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to update request for purchase.',
            'error' => $e->getMessage()
        ], 500);
    }
}


    // Update an existing request for purchase
    private function sendApproveNotification(User $creator, RequestForPurchase $requestForPurchase)
{
    $project = $requestForPurchase->project;
    $analysis = $requestForPurchase->analysis;
    $itemDescription = $analysis ? ($analysis->item_description ?: 'N/A') : 'N/A';

    $status = ucfirst($requestForPurchase->status); // Capitalize status
    $subject = "Your Purchase Request Has Been {$status}";
    $emailBody = "Dear {$creator->name},\n\n"
        . "Your purchase request for project '{$project->project_name}' has been {$requestForPurchase->status}:\n"
        . "Item: {$itemDescription}\n"
        . "Quantity Purchased: {$requestForPurchase->quantity_purchased}\n"
        . "Amount Purchased: {$requestForPurchase->amount_purchased}\n"
        . "Vendor Name: {$requestForPurchase->VendorName}\n"
        . "Vendor Account Number: {$requestForPurchase->VendorAccountNumber}\n"
        . "Vendor Contact: {$requestForPurchase->VendorContact}\n";

    // Include rejection reason if status is rejected
    if ($requestForPurchase->status === 'rejected' && $requestForPurchase->rejection_reason) {
        $emailBody .= "Rejection Reason: {$requestForPurchase->rejection_reason}\n";
    }

    $emailBody .= "\nThank you,\nTender Management System";

    try {
        Mail::raw($emailBody, function ($message) use ($creator, $subject) {
            $message->to($creator->email)->subject($subject);
        });
        \Log::info("Status notification sent successfully to {$creator->email} for request_for_id: {$requestForPurchase->request_for_id}, status: {$requestForPurchase->status}");
        return [
            'status' => true,
            'message' => "Notification sent to {$creator->email}"
        ];
    } catch (Exception $e) {
        \Log::error("Failed to send status notification to {$creator->email} for request_for_id: {$requestForPurchase->request_for_id}: " . $e->getMessage());
        return [
            'status' => false,
            'message' => "Failed to send notification to {$creator->email}: " . $e->getMessage()
        ];
    }
}

    // Delete a request for purchase
    public function destroy($request_for_id)
    {
        try {
            $requestForPurchase = RequestForPurchase::findOrFail($request_for_id);
            $analysis = Analysis::findOrFail($requestForPurchase->analysis_id);

            // Revert the deduction from analysis
            $analysis->update([
                'quantity' => $analysis->quantity + $requestForPurchase->quantity_purchased,
                'amount' => $analysis->amount + $requestForPurchase->amount_purchased,
            ]);

            $requestForPurchase->delete();

            return response()->json([
                'status' => true,
                'message' => 'Request for purchase deleted successfully.',
                'data' => [
                    'current_quantity' => $analysis->quantity,
                    'current_amount' => $analysis->amount
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete request for purchase.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}