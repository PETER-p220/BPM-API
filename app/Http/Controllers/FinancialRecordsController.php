<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FinancialRecordsController extends Controller
{
    /**
     * Get financial records list
     */
    public function index(Request $request)
    {
        try {
            $query = DB::table('financial_records')
                ->select([
                    'id',
                    'type',
                    'amount',
                    'currency',
                    'description',
                    'category',
                    'status',
                    'created_at',
                    'updated_at'
                ])
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $records = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $records,
                'message' => 'Financial records retrieved successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve financial records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get financial statistics
     */
    public function stats(Request $request)
    {
        try {
            $currentMonth = Carbon::now()->month;
            $currentYear = Carbon::now()->year;
            $lastMonth = Carbon::now()->subMonth()->month;
            $lastMonthYear = Carbon::now()->subMonth()->year;

            // Total records
            $totalRecords = DB::table('financial_records')->count();

            // Current month records
            $currentMonthRecords = DB::table('financial_records')
                ->whereMonth('created_at', $currentMonth)
                ->whereYear('created_at', $currentYear)
                ->count();

            // Last month records
            $lastMonthRecords = DB::table('financial_records')
                ->whereMonth('created_at', $lastMonth)
                ->whereYear('created_at', $lastMonthYear)
                ->count();

            // Revenue statistics
            $totalRevenue = DB::table('financial_records')
                ->where('type', 'income')
                ->sum('amount');

            $currentMonthRevenue = DB::table('financial_records')
                ->where('type', 'income')
                ->whereMonth('created_at', $currentMonth)
                ->whereYear('created_at', $currentYear)
                ->sum('amount');

            $lastMonthRevenue = DB::table('financial_records')
                ->where('type', 'income')
                ->whereMonth('created_at', $lastMonth)
                ->whereYear('created_at', $lastMonthYear)
                ->sum('amount');

            // Expense statistics
            $totalExpenses = DB::table('financial_records')
                ->where('type', 'expense')
                ->sum('amount');

            $currentMonthExpenses = DB::table('financial_records')
                ->where('type', 'expense')
                ->whereMonth('created_at', $currentMonth)
                ->whereYear('created_at', $currentYear)
                ->sum('amount');

            // Records by category
            $recordsByCategory = DB::table('financial_records')
                ->selectRaw('category, COUNT(*) as count, SUM(amount) as total')
                ->groupBy('category')
                ->get();

            // Monthly trend (last 6 months)
            $monthlyTrend = DB::table('financial_records')
                ->selectRaw('
                    DATE_FORMAT(created_at, "%Y-%m") as month,
                    SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as income,
                    SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as expense,
                    COUNT(*) as total_records
                ')
                ->where('created_at', '>=', Carbon::now()->subMonths(6))
                ->groupByRaw('DATE_FORMAT(created_at, "%Y-%m")')
                ->orderBy('month', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_records' => $totalRecords,
                        'current_month_records' => $currentMonthRecords,
                        'last_month_records' => $lastMonthRecords,
                    ],
                    'revenue' => [
                        'total' => $totalRevenue,
                        'current_month' => $currentMonthRevenue,
                        'last_month' => $lastMonthRevenue,
                        'currency' => 'TZS'
                    ],
                    'expenses' => [
                        'total' => $totalExpenses,
                        'current_month' => $currentMonthExpenses,
                        'currency' => 'TZS'
                    ],
                    'by_category' => $recordsByCategory,
                    'monthly_trend' => $monthlyTrend
                ],
                'message' => 'Financial statistics retrieved successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve financial statistics: ' . $e->getMessage()
            ], 500);
        }
    }
}
