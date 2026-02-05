<?php

namespace App\Http\Controllers;

use App\Models\ExtendRequest;
use App\Models\Analysis;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Exception;

class ExtendRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // List all extend requests
    public function index()
    {
        $extendRequests = ExtendRequest::with([
            'project:project_id,project_name',
            'analysis:analysis_id,item_description',
            'user:user_id,name'
        ])->orderBy('extend_id', 'desc')->get();

        return response()->json([
            'status' => true,
            'message' => 'Extend requests fetched successfully.',
            'data' => $extendRequests
        ], 200);
    }

    // Fetch extend requests for the logged-in user
    public function loggedUserExtentions(Request $request)
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }

            $extendRequests = ExtendRequest::with([
                'project:project_id,project_name',
                'analysis:analysis_id,item_description',
                'user:user_id,name'
            ])
            ->where('user_id', $userId)
            ->orderBy('extend_id', 'desc')
            ->get();

            if ($extendRequests->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'message' => 'No extend requests found for the logged-in user.',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'status' => true,
                'message' => 'Your extend requests fetched successfully.',
                'data' => $extendRequests
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch extend requests.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Store a new extend request
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,project_id',
            'analysis_id' => 'required|exists:analyses,analysis_id',
            'quantity_extended' => 'required|integer|min:1',
            'amount_extended' => 'required|numeric|min:0',
            'reason_for_extend' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors.',
                'errors' => $validator->errors()
            ], 400);
        }
        try {
            $analysis = Analysis::findOrFail($request->analysis_id);

            // Check if analysis has quantity and amount values
            if ($analysis->quantity === null || $analysis->quantity === '') {
                throw new Exception("Analysis record has no quantity value available. Please ensure the analysis has been properly set up with quantity and amount.");
            }
            
            if ($analysis->amount === null || $analysis->amount === '') {
                throw new Exception("Analysis record has no amount value available. Please ensure the analysis has been properly set up with quantity and amount.");
            }

            // Create the extend request
            $extendRequest = ExtendRequest::create([
                'project_id' => $request->project_id,
                'user_id' => Auth::id(),
                'analysis_id' => $request->analysis_id,
                'quantity_extended' => $request->quantity_extended,
                'amount_extended' => $request->amount_extended,
                'reason_for_extend' => $request->reason_for_extend,
                'status' => 'pending',
            ]);

            // Update analysis table: add quantity_extended, subtract amount_extended
            $newQuantity = $analysis->quantity + $request->quantity_extended;
            $newAmount = $analysis->amount - $request->amount_extended;

            if ($newAmount < 0) {
                throw new Exception("Amount extended ({$request->amount_extended}) exceeds available amount ({$analysis->amount}).");
            }

            $analysis->update([
                'quantity' => $newQuantity,
                'amount' => $newAmount,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Extend request created successfully.',
                'data' => $extendRequest->load(['project', 'analysis', 'user'])
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create extend request: ' . $e->getMessage()
            ], 500);
        }
    }

    // Show a specific extend request
    public function show($extend_id)
    {
        try {
            $extendRequest = ExtendRequest::with([
                'project:project_id,project_name',
                'analysis:analysis_id,item_description,quantity,amount',
                'user:user_id,name'
            ])->findOrFail($extend_id);

            return response()->json([
                'status' => true,
                'message' => 'Extend request fetched successfully.',
                'data' => $extendRequest
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Extend request not found.',
                'error' => $e->getMessage()
            ], 404);
        }
    }


    public function update(Request $request)
{
    $validator = Validator::make($request->all(), [
        'extend_id' => 'required|exists:extend_requests,extend_id',
        'project_id' => 'sometimes|exists:projects,project_id',
        'analysis_id' => 'sometimes|exists:analyses,analysis_id',
        'quantity_extended' => 'sometimes|integer|min:1',
        'amount_extended' => 'sometimes|numeric|min:0',
        'reason_for_extend' => 'sometimes|string|max:255',
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
        $extendRequest = ExtendRequest::findOrFail($request->extend_id);
        $data = [];

        // Fields to update
        $fields = [
            'project_id', 'analysis_id', 'quantity_extended', 'amount_extended',
            'reason_for_extend', 'status', 'rejection_reason'
        ];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->$field;
            }
        }

        // Track if status is changing to accepted or rejected
        $statusChanged = isset($data['status']) && $data['status'] !== $extendRequest->status && in_array($data['status'], ['accepted', 'rejected']);

        // Handle analysis updates if quantity or amount changes
        $newQuantity = null;
        $newAmount = null;
        if (isset($data['quantity_extended']) || isset($data['amount_extended'])) {
            $analysis = Analysis::findOrFail($request->analysis_id ?? $extendRequest->analysis_id);

            // Revert previous adjustments
            $analysis->quantity += $extendRequest->quantity_extended;
            $analysis->amount += $extendRequest->amount_extended;

            // Check new values
            $newQuantityExtended = $data['quantity_extended'] ?? $extendRequest->quantity_extended;
            $newAmountExtended = $data['amount_extended'] ?? $extendRequest->amount_extended;

            if ($analysis->amount < $newAmountExtended) {
                throw new Exception("Requested amount_extended ({$newAmountExtended}) exceeds available amount ({$analysis->amount}).");
            }

            // Apply new adjustments
            $newQuantity = $analysis->quantity - $newQuantityExtended;
            $newAmount = $analysis->amount - $newAmountExtended;
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

        $extendRequest->update($data);

        // Send notification if status changed to accepted or rejected
        if ($statusChanged) {
            $creator = User::findOrFail($extendRequest->user_id);
            $notificationResult = $this->sendApproveNotification($creator, $extendRequest);
            if (!$notificationResult['status']) {
                \Log::warning("Notification failure: " . $notificationResult['message']);
                // Continue execution even if notification fails
            }
        }

        $responseData = [
            'request' => $extendRequest->load(['project', 'analysis', 'user']),
            'current_quantity' => $newQuantity ?? $analysis->quantity ?? null,
            'quantity_extended' => $data['quantity_extended'] ?? $extendRequest->quantity_extended,
            'current_amount' => $newAmount ?? $analysis->amount ?? null,
            'amount_extended' => $data['amount_extended'] ?? $extendRequest->amount_extended,
        ];

        return response()->json([
            'status' => true,
            'message' => 'Extend request updated successfully.',
            'data' => $responseData
        ], 200);

    } catch (Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to update extend request.',
            'error' => $e->getMessage()
        ], 500);
    }
}

private function sendApproveNotification(User $creator, ExtendRequest $extendRequest)
{
    $project = $extendRequest->project;
    $analysis = $extendRequest->analysis;
    $itemDescription = $analysis ? ($analysis->item_description ?: 'N/A') : 'N/A';

    $status = ucfirst($extendRequest->status); // Capitalize status
    $subject = "Your Extend Request Has Been {$status}";
    $emailBody = "Dear {$creator->name},\n\n"
        . "Your extend request for project '{$project->project_name}' has been {$extendRequest->status}:\n"
        . "Item: {$itemDescription}\n"
        . "Reason: {$extendRequest->reason_for_extend}\n"
        . "Quantity Extended: {$extendRequest->quantity_extended}\n"
        . "Amount Extended: {$extendRequest->amount_extended}\n";

    // Include rejection reason if status is rejected
    if ($extendRequest->status === 'rejected' && $extendRequest->rejection_reason) {
        $emailBody .= "Rejection Reason: {$extendRequest->rejection_reason}\n";
    }

    $emailBody .= "\nThank you,\nTender Management System";

    try {
        Mail::raw($emailBody, function ($message) use ($creator, $subject) {
            $message->to($creator->email)->subject($subject);
        });
        \Log::info("Status notification sent successfully to {$creator->email} for extend_id: {$extendRequest->extend_id}, status: {$extendRequest->status}");
        return [
            'status' => true,
            'message' => "Notification sent to {$creator->email}"
        ];
    } catch (Exception $e) {
        \Log::error("Failed to send status notification to {$creator->email} for extend_id: {$extendRequest->extend_id}: " . $e->getMessage());
        return [
            'status' => false,
            'message' => "Failed to send notification to {$creator->email}: " . $e->getMessage()
        ];
    }
}


    // Delete an extend request
    public function destroy($extend_id)
    {
        try {
            $extendRequest = ExtendRequest::findOrFail($extend_id);
            $analysis = Analysis::findOrFail($extendRequest->analysis_id);

            // Revert the changes from analysis
            $analysis->update([
                'quantity' => $analysis->quantity - $extendRequest->quantity_extended,
                'amount' => $analysis->amount + $extendRequest->amount_extended,
            ]);

            $extendRequest->delete();

            return response()->json([
                'status' => true,
                'message' => 'Extend request deleted successfully.',
                'data' => [
                    'current_quantity' => $analysis->quantity,
                    'current_amount' => $analysis->amount
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete extend request.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Helper: Notify the creator on creation
    private function sendCreatorNotification(User $user, ExtendRequest $extendRequest, $currentQuantity, $currentAmount)
    {
        $subject = "Your Extend Request Submission: {$extendRequest->reason_for_extend}";
        $emailBody = "Dear {$user->name},\n\n"
            . "You have successfully submitted an extend request:\n"
            . "Project: {$extendRequest->project->project_name}\n"
            . "Reason: {$extendRequest->reason_for_extend}\n"
            . "Quantity Extended: {$extendRequest->quantity_extended}\n"
            . "Current Quantity: {$currentQuantity}\n"
            . "Amount Extended: {$extendRequest->amount_extended}\n"
            . "Current Amount: {$currentAmount}\n"
            . "Status: {$extendRequest->status}\n"
            . "Please await approval from the admin.\n\n"
            . "Thank you,\nTender Management System";

        try {
            Mail::raw($emailBody, function ($message) use ($user, $subject) {
                $message->to($user->email)->subject($subject);
            });
            \Log::info("Notification sent successfully to creator {$user->email} for extend_id: {$extendRequest->extend_id}");
            return [
                'status' => true,
                'message' => "Notification sent to {$user->email}"
            ];
        } catch (Exception $e) {
            \Log::error("Failed to send notification to creator {$user->email} for extend_id: {$extendRequest->extend_id}: " . $e->getMessage());
            return [
                'status' => false,
                'message' => "Failed to send notification to {$user->email}: " . $e->getMessage()
            ];
        }
    }

    // Helper: Notify admin on creation
    private function sendAdminNotification(User $admin, ExtendRequest $extendRequest, $submitterName)
    {
        $project = $extendRequest->project;
        $subject = "New Extend Request Submitted by {$submitterName}";
        $emailBody = "Dear {$admin->name},\n\n"
            . "A new extend request has been submitted by {$submitterName}:\n"
            . "Project: {$project->project_name}\n"
            . "Reason: {$extendRequest->reason_for_extend}\n"
            . "Quantity Extended: {$extendRequest->quantity_extended}\n"
            . "Amount Extended: {$extendRequest->amount_extended}\n"
            . "Status: {$extendRequest->status}\n"
            . "Please log in to review and accept or reject it.\n\n"
            . "Thank you,\nTender Management System";

        try {
            Mail::raw($emailBody, function ($message) use ($admin, $subject) {
                $message->to($admin->email)->subject($subject);
            });
            \Log::info("Admin notification sent successfully to {$admin->email} for extend_id: {$extendRequest->extend_id}");
            return [
                'status' => true,
                'message' => "Notification sent to {$admin->email}"
            ];
        } catch (Exception $e) {
            \Log::error("Failed to send admin notification to {$admin->email} for extend_id: {$extendRequest->extend_id}: " . $e->getMessage());
            return [
                'status' => false,
                'message' => "Failed to send notification to {$admin->email}: " . $e->getMessage()
            ];
        }
    }

}