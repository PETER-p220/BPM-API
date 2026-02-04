<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{

   public function index()
{
    try {
        // Fetch all payments along with the user and project details
        $payments = Payment::with(['user', 'project'])->get();

        // Format the response to include user name and match data structure
        $payments = $payments->map(function ($payment) {
            return [
                'payment_id' => $payment->payment_id,
                'user_id' => $payment->user_id,
                'project_id' => $payment->project_id,
                'amount_paid' => $payment->amount_paid,
                'payment_status' => $payment->payment_status,
                'payment_category' => $payment->payment_category,
                'is_approved' => $payment->is_approved,
                'if_debt' => $payment->if_debt,
                'description' => $payment->description,
                'client_name' => $payment->client_name,
                'ref_number' => $payment->ref_number,
                'created_at' => $payment->created_at,
                'updated_at' => $payment->updated_at,
                'user' => [
                    'user_id' => $payment->user->user_id,
                    'name' => $payment->user->name, // Include user name
                    'role_id' => $payment->user->role_id,
                    'department_id' => $payment->user->department_id,
                    'status' => $payment->user->status,
                    'email' => $payment->user->email,
                    'created_at' => $payment->user->created_at,
                    'updated_at' => $payment->user->updated_at,
                ],
                'project' => [
                    'project_id' => $payment->project->project_id,
                    'project_name' => $payment->project->project_name,
                    'tender_id' => $payment->project->tender_id,
                    'description' => $payment->project->description,
                    'budget' => $payment->project->budget,
                    'user_id' => $payment->project->user_id,
                    'created_by' => $payment->project->created_by,
                    'document' => $payment->project->document,
                    'project_status' => $payment->project->project_status,
                    'start_date' => $payment->project->start_date,
                    'end_date' => $payment->project->end_date,
                    'is_viewed' => $payment->project->is_viewed,
                    'contract' => $payment->project->contract,
                    'performance_status' => $payment->project->performance_status,
                    'created_at' => $payment->project->created_at,
                    'updated_at' => $payment->project->updated_at,
                ],
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Payments retrieved successfully',
            'data' => $payments,
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'An error occurred while fetching payments.',
            'error' => $e->getMessage(),
        ], 500);
    }
}



   // Fetch a specific payment by payment_id
public function show($payment_id)
{
    $payment = Payment::with('user', 'project')->where('payment_id', $payment_id)->first();

    if (!$payment) {
        return response()->json([
            'status' => 'error',
            'message' => 'Payment not found',
        ], 404);
    }

    return response()->json([
        'status' => 'success',
        'message' => 'Payment retrieved successfully',
        'data' => $payment,
    ], 200);
}

    // Create a new payment
    public function store(Request $request)
{
    try {
        $validator = Validator::make($request->all(), [
            'tender_id' => 'required|string|max:255',
            'excel_file' => 'required|file|mimes:xlsx,xls|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenderId = $request->input('tender_id');
        $file = $request->file('excel_file');

        Excel::import(new PriceSchedulesImport($tenderId), $file);

        Log::info('Price schedules imported successfully', [
            'tender_id' => $tenderId,
            'user_id' => Auth::id(),
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Price schedules imported successfully',
        ], 200);
    } catch (\Exception $e) {
        Log::error('Error importing price schedules', [
            'tender_id' => $request->input('tender_id'),
            'user_id' => Auth::id(),
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'status' => 500,
            'message' => 'Error importing price schedules',
            'error' => $e->getMessage(),
        ], 500);
    }
}


    // Update an existing payment
public function update(Request $request, $payment_id)
{
    // Find the payment by payment_id
    $payment = Payment::where('payment_id', $payment_id)->first();

    if (!$payment) {
        return response()->json([
            'status' => 'error',
            'message' => 'Payment not found',
        ], 404);
    }

    // Validate the request
    $validatedData = $request->validate([
        'amount_paid' => 'nullable|string',
        'payment_status' => 'nullable|in:partial-payed,total-payed',
        'payment_category' => 'nullable|in:credit,cash',
        'is_approved' => 'nullable|string',
        'if_debt' => 'nullable|string',
        'description' => 'nullable|string',
        'client_name' => 'nullable|string|max:255',
        'ref_number' => 'nullable|string|max:255',
    ]);

    // Update the payment record
    $payment->update($validatedData);

    return response()->json([
        'status' => 'success',
        'message' => 'Payment updated successfully',
        'data' => $payment,
    ], 200);
}



    // Delete a payment
public function destroy($payment_id)
{
    // Find the payment by payment_id
    $payment = Payment::where('payment_id', $payment_id)->first();

    if (!$payment) {
        return response()->json([
            'status' => 'error',
            'message' => 'Payment not found',
        ], 404);
    }

    // Delete the payment
    $payment->delete();

    return response()->json([
        'status' => 'success',
        'message' => 'Payment deleted successfully',
    ], 200);
}


}
