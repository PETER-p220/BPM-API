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

        try {
            $analysis = Analysis::findOrFail($request->analysis_id);

            // Check if analysis has quantity and amount values
            if ($analysis->quantity === null || $analysis->quantity === '') {
                throw new Exception("Analysis record has no quantity value available. Please ensure the analysis has been properly set up with quantity and amount.");
            }
            
            if ($analysis->amount === null || $analysis->amount === '') {
                throw new Exception("Analysis record has no amount value available. Please ensure the analysis has been properly set up with quantity and amount.");
            }

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

            return response()->json([
                'status' => true,
                'message' => 'Request for purchase created successfully.',
                'data' => $requestForPurchase->load(['project', 'analysis', 'user'])
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create request for purchase: ' . $e->getMessage()
            ], 500);
        }
    }

    // Show a specific request for purchase
    public function show($request_for_id)
    {
        try {
            $request = RequestForPurchase::with([
                'project:project_id,project_name',
                'analysis:analysis_id,item_description',
                'user:user_id,name'
            ])->findOrFail($request_for_id);

            return response()->json([
                'status' => true,
                'message' => 'Request for purchase fetched successfully.',
                'data' => $request
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch request for purchase.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Update a request for purchase
    public function update(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'request_for_id' => 'required|integer|exists:request_for_purchases,request_for_id',
                'status' => 'required|in:pending,accepted,rejected',
                'rejection_reason' => 'required_if:status,rejected|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation errors.',
                    'errors' => $validator->errors()
                ], 400);
            }

            $requestForPurchase = RequestForPurchase::findOrFail($request->request_for_id);

            $updateData = [
                'status' => $request->status
            ];

            // Only set rejection_reason if status is rejected, otherwise use empty string
            if ($request->status === 'rejected') {
                $updateData['rejection_reason'] = $request->rejection_reason;
            } else {
                $updateData['rejection_reason'] = '';
            }

            $requestForPurchase->update($updateData);

            return response()->json([
                'status' => true,
                'message' => 'Request for purchase updated successfully.',
                'data' => $requestForPurchase->load(['project', 'analysis', 'user'])
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update request for purchase.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Delete a request for purchase
    public function destroy($request_for_id)
    {
        try {
            $requestForPurchase = RequestForPurchase::findOrFail($request_for_id);
            $requestForPurchase->delete();

            return response()->json([
                'status' => true,
                'message' => 'Request for purchase deleted successfully.'
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
