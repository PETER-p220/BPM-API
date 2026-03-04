<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Models\BudgetAllocation;

// Test endpoint (remove this in production)
Route::get('/test/budget', function() {
    try {
        // Test users table
        $users = User::limit(5)->get(['user_id', 'name', 'department_id']);
        
        // Test budget allocations table
        $budgets = BudgetAllocation::limit(5)->get();
        
        return response()->json([
            'status' => 'success',
            'users_count' => $users->count(),
            'budgets_count' => $budgets->count(),
            'sample_users' => $users,
            'sample_budgets' => $budgets
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
});

Route::get('/test/budget/create', function(Request $request) {
    try {
        // Test budget creation without authentication
        $budget = BudgetAllocation::create([
            'department_id' => '1', // sample user_id
            'allocated_amount' => 1000000,
            'spent_amount' => 0,
            'period' => 'monthly',
            'description' => 'Test budget',
            'fiscal_year' => '2026',
            'status' => 'pending',
            'created_by' => 1, // sample user_id
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => null
        ]);
        
        return response()->json([
            'status' 'success',
            'message' => 'Test budget created',
            'budget' => $budget
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
});
