<?php

namespace App\Http\Controllers;

use App\Models\FinancialRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class FinancialRequestController extends Controller
{
    // List current user's financial requests
    public function index()
    {
        $user = Auth::user();
        $requests = FinancialRequest::with('user:user_id,name')
            ->where('user_id', $user->user_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['status' => true, 'data' => $requests]);
    }

    // Submit a new financial request
    public function store(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:255',
            'category'    => 'required|in:reimbursement,advance,operational,travel,other',
            'amount'      => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $financialRequest = FinancialRequest::create([
            'user_id'     => $user->user_id,
            'title'       => $request->title,
            'category'    => $request->category,
            'amount'      => $request->amount,
            'description' => $request->description,
            'status'      => 'pending',
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Financial request submitted successfully.',
            'data'    => $financialRequest,
        ], 201);
    }
}
