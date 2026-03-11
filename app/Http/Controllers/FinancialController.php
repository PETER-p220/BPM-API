<?php

namespace App\Http\Controllers;

use App\Models\FinancialRecord;
use App\Models\SystemHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade as PDF;

class FinancialController extends Controller
{
    /**
     * Display a listing of financial records.
     */
    public function index(Request $request)
    {
        try {
            $query = FinancialRecord::query();

            // Apply filters
            if ($request->has('type') && $request->type) {
                $query->where('type', $request->type);
            }

            if ($request->has('category') && $request->category) {
                $query->where('category', $request->category);
            }

            if ($request->has('date') && $request->date) {
                $query->whereDate('date', $request->date);
            }

            // Search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'LIKE', "%{$search}%")
                      ->orWhere('reference', 'LIKE', "%{$search}%");
                });
            }

            $records = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'status' => 'success',
                'data' => $records
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch financial records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created financial record.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'required|date',
                'description' => 'required|string|max:255',
                'reference' => 'nullable|string|max:100',
                'type' => 'required|in:income,expense',
                'category' => 'required|in:sales,services,operations,salary,utilities,maintenance',
                'amount' => 'required|numeric|min:0',
                'status' => 'required|in:pending,verified,approved'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $record = FinancialRecord::create([
                'date' => $request->date,
                'description' => $request->description,
                'reference' => $request->reference,
                'type' => $request->type,
                'category' => $request->category,
                'amount' => $request->amount,
                'status' => $request->status,
                'created_by' => Auth::id()
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Financial record created successfully',
                'data' => $record
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create financial record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified financial record.
     */
    public function show($id)
    {
        try {
            $record = FinancialRecord::findOrFail($id);
            
            return response()->json([
                'status' => 'success',
                'data' => $record
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Financial record not found'
            ], 404);
        }
    }

    /**
     * Update the specified financial record.
     */
    public function update(Request $request, $id)
    {
        try {
            $record = FinancialRecord::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'date' => 'required|date',
                'description' => 'required|string|max:255',
                'reference' => 'nullable|string|max:100',
                'type' => 'required|in:income,expense',
                'category' => 'required|in:sales,services,operations,salary,utilities,maintenance',
                'amount' => 'required|numeric|min:0',
                'status' => 'required|in:pending,verified,approved'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $record->update([
                'date' => $request->date,
                'description' => $request->description,
                'reference' => $request->reference,
                'type' => $request->type,
                'category' => $request->category,
                'amount' => $request->amount,
                'status' => $request->status
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Financial record updated successfully',
                'data' => $record
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update financial record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified financial record.
     */
    public function destroy($id)
    {
        try {
            $record = FinancialRecord::findOrFail($id);
            $record->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Financial record deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete financial record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get financial records statistics.
     */
    public function getStats()
    {
        try {
            $stats = [
                'totalRecords' => FinancialRecord::count(),
                'totalIncome' => FinancialRecord::where('type', 'income')->sum('amount'),
                'totalExpenses' => FinancialRecord::where('type', 'expense')->sum('amount'),
                'pendingRecords' => FinancialRecord::where('status', 'pending')->count(),
                'verifiedRecords' => FinancialRecord::where('status', 'verified')->count(),
                'approvedRecords' => FinancialRecord::where('status', 'approved')->count(),
                'systemHealth' => $this->getSystemHealthStatus()
            ];

            return response()->json([
                'status' => 'success',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            \Log::error('Stats Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->user()?->user_id
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch financial stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system health status.
     */
    private function getSystemHealthStatus()
    {
        try {
            // Check database connection
            $dbStatus = DB::connection()->getPdo() ? 'healthy' : 'unhealthy';
            
            // Check data integrity
            $totalRecords = FinancialRecord::count();
            $recordsWithNullAmount = FinancialRecord::whereNull('amount')->count();
            $dataIntegrity = $totalRecords > 0 ? (($totalRecords - $recordsWithNullAmount) / $totalRecords) * 100 : 100;
            
            // Overall system health
            $systemHealth = ($dbStatus === 'healthy' && $dataIntegrity >= 95) ? 'Healthy' : 'Warning';

            return [
                'status' => $systemHealth,
                'database' => $dbStatus,
                'dataIntegrity' => round($dataIntegrity, 2),
                'totalRecords' => $totalRecords
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'Error',
                'database' => 'unknown',
                'dataIntegrity' => 0,
                'totalRecords' => 0
            ];
        }
    }

    /**
     * Export financial records.
     */
    public function export(Request $request)
    {
        try {
            $query = FinancialRecord::query();

            // Apply same filters as index method
            if ($request->has('type') && $request->type) {
                $query->where('type', $request->type);
            }

            if ($request->has('category') && $request->category) {
                $query->where('category', $request->category);
            }

            if ($request->has('date') && $request->date) {
                $query->whereDate('date', $request->date);
            }

            $records = $query->orderBy('created_at', 'desc')->get();

            // Convert to CSV format
            $csv = "Date,Description,Reference,Type,Category,Amount,Status,Created At\n";
            
            foreach ($records as $record) {
                $csv .= "{$record->date},{$record->description},{$record->reference},{$record->type},{$record->category},{$record->amount},{$record->status},{$record->created_at}\n";
            }

            $filename = "financial_records_" . date('Y-m-d_H-i-s') . ".csv";
            
            return response($csv)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to export financial records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * CEO specific financial records view.
     */
    public function ceoIndex(Request $request)
    {
        try {
            $query = FinancialRecord::query();

            // Apply filters
            if ($request->has('type') && $request->type) {
                $query->where('type', $request->type);
            }

            if ($request->has('category') && $request->category) {
                $query->where('category', $request->category);
            }

            if ($request->has('date') && $request->date) {
                $query->whereDate('date', $request->date);
            }

            // Search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'LIKE', "%{$search}%")
                      ->orWhere('reference', 'LIKE', "%{$search}%");
                });
            }

            $records = $query->with(['creator', 'approver'])
                             ->orderBy('created_at', 'desc')
                             ->get();

            return response()->json([
                'status' => 'success',
                'data' => $records,
                'summary' => $this->getFinancialSummary()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch financial records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * CEO specific financial statistics.
     */
    public function ceoStats()
    {
        try {
            $stats = [
                'totalRecords' => FinancialRecord::count(),
                'totalIncome' => FinancialRecord::where('type', 'income')->sum('amount'),
                'totalExpenses' => FinancialRecord::where('type', 'expense')->sum('amount'),
                'netBalance' => FinancialRecord::where('type', 'income')->sum('amount') - FinancialRecord::where('type', 'expense')->sum('amount'),
                'pendingRecords' => FinancialRecord::where('status', 'pending')->count(),
                'verifiedRecords' => FinancialRecord::where('status', 'verified')->count(),
                'approvedRecords' => FinancialRecord::where('status', 'approved')->count(),
                'systemHealth' => $this->getSystemHealthStatus(),
                'monthlyTrends' => $this->getMonthlyTrends(),
                'categoryBreakdown' => $this->getCategoryBreakdown(),
                'userActivity' => $this->getUserActivityStats()
            ];

            return response()->json([
                'status' => 'success',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            \Log::error('CEO Stats Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->user()?->user_id
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch financial stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin specific financial records view.
     */
    public function adminIndex(Request $request)
    {
        try {
            $query = FinancialRecord::query();

            // Apply filters
            if ($request->has('type') && $request->type) {
                $query->where('type', $request->type);
            }

            if ($request->has('category') && $request->category) {
                $query->where('category', $request->category);
            }

            if ($request->has('date') && $request->date) {
                $query->whereDate('date', $request->date);
            }

            // Search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'LIKE', "%{$search}%")
                      ->orWhere('reference', 'LIKE', "%{$search}%");
                });
            }

            $records = $query->with(['creator', 'approver'])
                             ->orderBy('created_at', 'desc')
                             ->get();

            return response()->json([
                'status' => 'success',
                'data' => $records,
                'summary' => $this->getFinancialSummary()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch financial records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin specific financial statistics.
     */
    public function adminStats()
    {
        try {
            $stats = [
                'totalRecords' => FinancialRecord::count(),
                'totalIncome' => FinancialRecord::where('type', 'income')->sum('amount'),
                'totalExpenses' => FinancialRecord::where('type', 'expense')->sum('amount'),
                'netBalance' => FinancialRecord::where('type', 'income')->sum('amount') - FinancialRecord::where('type', 'expense')->sum('amount'),
                'pendingRecords' => FinancialRecord::where('status', 'pending')->count(),
                'verifiedRecords' => FinancialRecord::where('status', 'verified')->count(),
                'approvedRecords' => FinancialRecord::where('status', 'approved')->count(),
                'systemHealth' => $this->getSystemHealthStatus(),
                'monthlyTrends' => $this->getMonthlyTrends(),
                'categoryBreakdown' => $this->getCategoryBreakdown(),
                'userActivity' => $this->getUserActivityStats()
            ];

            return response()->json([
                'status' => 'success',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            \Log::error('Admin Stats Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->user()?->user_id
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch financial stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export financial records to PDF.
     */
    public function exportPdf(Request $request)
    {
        try {
            $query = FinancialRecord::query();

            // Apply same filters as index method
            if ($request->has('type') && $request->type) {
                $query->where('type', $request->type);
            }

            if ($request->has('category') && $request->category) {
                $query->where('category', $request->category);
            }

            if ($request->has('date') && $request->date) {
                $query->whereDate('date', $request->date);
            }

            $records = $query->with(['creator', 'approver'])
                             ->orderBy('created_at', 'desc')
                             ->get();

            $summary = $this->getFinancialSummary();

            $pdf = PDF::loadView('exports.financial_records_pdf', [
                'records' => $records,
                'summary' => $summary,
                'filters' => $request->all(),
                'exportDate' => now()
            ]);

            $filename = "financial_records_" . date('Y-m-d_H-i-s') . ".pdf";
            
            return $pdf->download($filename);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to export PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export financial records to Excel.
     */
    public function exportExcel(Request $request)
    {
        try {
            $query = FinancialRecord::query();

            // Apply same filters as index method
            if ($request->has('type') && $request->type) {
                $query->where('type', $request->type);
            }

            if ($request->has('category') && $request->category) {
                $query->where('category', $request->category);
            }

            if ($request->has('date') && $request->date) {
                $query->whereDate('date', $request->date);
            }

            $records = $query->with(['creator', 'approver'])
                             ->orderBy('created_at', 'desc')
                             ->get();

            $filename = "financial_records_" . date('Y-m-d_H-i-s') . ".xlsx";
            
            return Excel::download(new \App\Exports\FinancialRecordsExport($records), $filename);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to export Excel: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get financial summary.
     */
    private function getFinancialSummary()
    {
        return [
            'totalIncome' => FinancialRecord::where('type', 'income')->sum('amount'),
            'totalExpenses' => FinancialRecord::where('type', 'expense')->sum('amount'),
            'netBalance' => FinancialRecord::where('type', 'income')->sum('amount') - FinancialRecord::where('type', 'expense')->sum('amount'),
            'totalRecords' => FinancialRecord::count(),
            'approvedRecords' => FinancialRecord::where('status', 'approved')->count(),
            'pendingRecords' => FinancialRecord::where('status', 'pending')->count()
        ];
    }

    /**
     * Get monthly trends.
     */
    private function getMonthlyTrends()
    {
        $trends = FinancialRecord::selectRaw('
                DATE_FORMAT(date, "%Y-%m") as month,
                SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as expenses,
                COUNT(*) as total_records
            ')
            ->where('date', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return $trends;
    }

    /**
     * Get category breakdown.
     */
    private function getCategoryBreakdown()
    {
        $breakdown = FinancialRecord::selectRaw('
                category,
                SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as expenses,
                COUNT(*) as count
            ')
            ->groupBy('category')
            ->orderBy('category')
            ->get();

        return $breakdown;
    }

    /**
     * Get user activity statistics.
     */
    private function getUserActivityStats()
    {
        $activity = FinancialRecord::leftJoin('users', 'financial_records.created_by', '=', 'users.user_id')
            ->selectRaw('
                financial_records.created_by, 
                COUNT(*) as records_created, 
                SUM(financial_records.amount) as total_amount,
                users.name as creator_name,
                users.email as creator_email
            ')
            ->where('financial_records.created_at', '>=', now()->subMonths(3))
            ->groupBy('financial_records.created_by', 'users.name', 'users.email')
            ->orderBy('records_created', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'created_by' => $item->created_by,
                    'records_created' => $item->records_created,
                    'total_amount' => $item->total_amount,
                    'creator' => [
                        'name' => $item->creator_name ?: 'Unknown User',
                        'email' => $item->creator_email ?: 'unknown@example.com'
                    ]
                ];
            });

        return $activity;
    }
}
