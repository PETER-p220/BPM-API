<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\BudgetAllocation;
use App\Models\AwardedTender;
use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class BudgetManagementController extends Controller
{
    protected function normalizeBudgetTarget(BudgetAllocation $budget): array
    {
        $budget->loadMissing([
            'department:department_id,name',
            'user:user_id,name,department_id',
            'project:project_id,project_name,tender_id,budget',
            'project.tender:tender_id,title,tender_number',
            'awardedTender:award_id,tender_id,user_id,awarded_document',
            'awardedTender.tender:tender_id,title,tender_number,procurement_entity',
            'creator:user_id,name',
            'approver:user_id,name',
        ]);

        $allocatedAmount = (float) ($budget->allocated_amount ?? 0);
        $spentAmount = (float) ($budget->spent_amount ?? 0);
        $utilization = $allocatedAmount > 0 ? ($spentAmount / $allocatedAmount) * 100 : 0;
        $type = $budget->allocation_type ?? BudgetAllocation::TYPE_DEPARTMENT;

        $department = $budget->department;
        $fallbackUser = $budget->user;
        $project = $budget->project;
        $award = $budget->awardedTender;
        $awardTender = $award?->tender;

        $targetLabel = match ($type) {
            BudgetAllocation::TYPE_PROJECT => $project?->project_name ?? 'Unknown Project',
            BudgetAllocation::TYPE_AWARDED_TENDER => $awardTender?->title ?? 'Unknown Awarded Tender',
            default => $department?->name ?? $fallbackUser?->name ?? 'Unassigned Department',
        };

        $targetMeta = match ($type) {
            BudgetAllocation::TYPE_PROJECT => collect([
                $project?->tender?->title,
                $project?->project_status,
            ])->filter()->implode(' • '),
            BudgetAllocation::TYPE_AWARDED_TENDER => collect([
                $awardTender?->tender_number,
                $awardTender?->procurement_entity,
            ])->filter()->implode(' • '),
            default => null,
        };

        return [
            'id' => $budget->id,
            'target_id' => match ($type) {
                BudgetAllocation::TYPE_PROJECT => $budget->project_id,
                BudgetAllocation::TYPE_AWARDED_TENDER => $budget->award_id,
                default => $department?->department_id ?? $fallbackUser?->department_id ?? $budget->department_id,
            },
            'type' => $type,
            'type_label' => $budget->target_type_label,
            'name' => $targetLabel,
            'target_label' => $targetLabel,
            'target_meta' => $targetMeta,
            'department_id' => $department?->department_id ?? $fallbackUser?->department_id ?? $budget->department_id,
            'project_id' => $budget->project_id,
            'award_id' => $budget->award_id,
            'allocated' => $allocatedAmount,
            'spent' => $spentAmount,
            'remaining' => $allocatedAmount - $spentAmount,
            'utilization_percentage' => round($utilization, 2),
            'color' => $utilization > 90 ? 'bg-red-500' : ($utilization > 70 ? 'bg-amber-500' : 'bg-green-500'),
            'project_count' => 0,
            'status' => $utilization > 90 ? 'Near Limit' : ($utilization > 70 ? 'On Track' : 'Under Budget'),
            'progress_color' => $utilization > 90 ? 'bg-red-500' : ($utilization > 70 ? 'bg-blue-500' : 'bg-green-500'),
            'status_color' => $utilization > 90 ? 'text-red-600' : ($utilization > 70 ? 'text-blue-600' : 'text-green-600'),
            'created_by' => $budget->creator?->name ?? 'Unknown User',
            'approved_by' => $budget->approver?->name,
            'approved_at' => $budget->approved_at,
            'allocated_amount' => $allocatedAmount,
            'spent_amount' => $spentAmount,
            'period' => $budget->period,
            'fiscal_year' => $budget->fiscal_year,
            'description' => $budget->description,
            'award_document' => $award?->awarded_document,
        ];
    }

    protected function validateBudgetPayload(Request $request): array
    {
        $validated = $request->validate([
            'allocation_type' => 'required|in:department,project,awarded_tender',
            'department_id' => 'nullable|integer|exists:departments,department_id',
            'project_id' => 'nullable|integer|exists:projects,project_id',
            'award_id' => 'nullable|integer|exists:awarded_tenders,award_id',
            'allocated_amount' => 'required|numeric|min:0',
            'period' => 'required|in:monthly,quarterly,yearly',
            'description' => 'nullable|string',
            'fiscal_year' => ['required', 'regex:/^\\d{4}$/'],
        ]);

        $type = $validated['allocation_type'];
        $typeMap = [
            BudgetAllocation::TYPE_DEPARTMENT => 'department_id',
            BudgetAllocation::TYPE_PROJECT => 'project_id',
            BudgetAllocation::TYPE_AWARDED_TENDER => 'award_id',
        ];

        $requiredField = $typeMap[$type];
        if (empty($validated[$requiredField])) {
            throw ValidationException::withMessages([
                $requiredField => [ucfirst(str_replace('_', ' ', $requiredField)) . ' is required for allocation type ' . $type . '.'],
            ]);
        }

        foreach ($typeMap as $field) {
            if ($field !== $requiredField) {
                $validated[$field] = null;
            }
        }

        return $validated;
    }

    /**
     * Get budget overview for CEO dashboard
     */
    public function getBudgetOverview(Request $request)
    {
        try {
            $fiscalYear = $request->get('fiscal_year', date('Y'));
            
            // Get only approved/active budgets
            $budgetAllocations = BudgetAllocation::where('fiscal_year', $fiscalYear)
                ->whereIn('status', ['approved', 'active'])
                ->with([
                    'department:department_id,name',
                    'user:user_id,name,department_id',
                    'project:project_id,project_name,tender_id,budget,project_status',
                    'project.tender:tender_id,title,tender_number',
                    'awardedTender:award_id,tender_id,user_id,awarded_document',
                    'awardedTender.tender:tender_id,title,tender_number,procurement_entity',
                    'creator:user_id,name',
                    'approver:user_id,name'
                ])
                ->get();
            
            $departments = $budgetAllocations->map(fn ($budget) => $this->normalizeBudgetTarget($budget));
            
            // Calculate totals
            $totalAllocated = $departments->sum('allocated');
            $totalSpent = $departments->sum('spent');
            $overallUtilization = $totalAllocated > 0 ? ($totalSpent / $totalAllocated) * 100 : 0;
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'departments' => $departments,
                    'total_allocated' => round($totalAllocated, 2),
                    'total_spent' => round($totalSpent, 2),
                    'overall_utilization' => round($overallUtilization, 1),
                    'fiscal_year' => $fiscalYear
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch budget overview: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Return approved/active budget allocations for payment selection
     */
    public function getApprovedBudgets(Request $request)
    {
        try {
            $fiscalYear = $request->get('fiscal_year');

            $query = BudgetAllocation::whereIn('status', ['approved', 'active'])
                ->with([
                    'department:department_id,name',
                    'user:user_id,name,department_id',
                    'project:project_id,project_name,tender_id,budget,project_status',
                    'project.tender:tender_id,title,tender_number',
                    'awardedTender:award_id,tender_id,user_id,awarded_document',
                    'awardedTender.tender:tender_id,title,tender_number,procurement_entity',
                    'creator:user_id,name'
                ]);

            if ($fiscalYear) {
                $query->where('fiscal_year', $fiscalYear);
            }

            $budgets = $query->orderBy('created_at', 'desc')->get()->map(function ($b) {
                $normalized = $this->normalizeBudgetTarget($b);

                return [
                    'id' => $normalized['id'],
                    'type' => $normalized['type'],
                    'type_label' => $normalized['type_label'],
                    'department_id' => $normalized['department_id'],
                    'project_id' => $normalized['project_id'],
                    'award_id' => $normalized['award_id'],
                    'department_name' => $normalized['target_label'],
                    'target_label' => $normalized['target_label'],
                    'target_meta' => $normalized['target_meta'],
                    'allocated_amount' => $normalized['allocated_amount'],
                    'spent_amount' => $normalized['spent_amount'],
                    'remaining' => $normalized['remaining'],
                    'period' => $normalized['period'],
                    'fiscal_year' => $normalized['fiscal_year'],
                    'status' => $b->status,
                    'description' => $normalized['description'],
                    'created_by' => $normalized['created_by'],
                    'award_document' => $normalized['award_document'],
                ];
            });

            return response()->json(['status' => 'success', 'data' => $budgets], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Record a payment against an approved budget (increments spent_amount)
     */
    public function addPayment(Request $request)
    {
        try {
            $request->validate([
                'budget_id'   => 'required|integer|exists:budget_allocations,id',
                'amount'      => 'required|numeric|min:0.01',
                'description' => 'nullable|string|max:500',
                'paid_at'     => 'nullable|date',
            ]);

            $budget = BudgetAllocation::findOrFail($request->budget_id);

            if ($budget->status !== 'approved' && $budget->status !== 'active') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Payments can only be recorded against approved or active budgets.'
                ], 422);
            }

            $newSpent = (float) $budget->spent_amount + (float) $request->amount;

            $budget->update([
                'spent_amount' => $newSpent,
            ]);

            $budget->load(['department:department_id,name', 'creator:user_id,name']);

            $normalized = $this->normalizeBudgetTarget($budget);

            return response()->json([
                'status'  => 'success',
                'message' => 'Payment recorded successfully',
                'data'    => [
                    'budget_id'    => $budget->id,
                    'target'       => $normalized['target_label'],
                    'type'         => $normalized['type'],
                    'spent_amount' => $budget->spent_amount,
                    'allocated'    => $budget->allocated_amount,
                    'paid_at'      => $request->paid_at ?? now()->toDateString(),
                    'description'  => $request->description,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to record payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new budget allocation (Accountant role)
     */
    public function createBudget(Request $request)
    {
        try {
            $validated = $this->validateBudgetPayload($request);

            $budget = BudgetAllocation::create([
                'department_id' => $validated['department_id'] ?? null,
                'allocation_type' => $validated['allocation_type'],
                'project_id' => $validated['project_id'] ?? null,
                'award_id' => $validated['award_id'] ?? null,
                'allocated_amount' => $validated['allocated_amount'],
                'spent_amount' => 0,
                'period' => $validated['period'],
                'description' => $validated['description'] ?? null,
                'fiscal_year' => $validated['fiscal_year'],
                'status' => 'pending',
                'created_by' => auth()->id()
            ]);

            $budget->load([
                'department:department_id,name',
                'project:project_id,project_name,tender_id,budget,project_status',
                'project.tender:tender_id,title,tender_number',
                'awardedTender:award_id,tender_id,user_id,awarded_document',
                'awardedTender.tender:tender_id,title,tender_number,procurement_entity',
                'creator:user_id,name'
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Budget created successfully and pending CEO approval',
                'data' => $this->normalizeBudgetTarget($budget)
            ], 201);
            
        } catch (\Exception $e) {
            \Log::error('Budget creation failed:', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create budget: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Create budget directly as approved (CEO role — no approval step needed)
     */
    public function createDirectBudget(Request $request)
    {
        try {
            $validated = $this->validateBudgetPayload($request);

            $budget = BudgetAllocation::create([
                'department_id'   => $validated['department_id'] ?? null,
                'allocation_type' => $validated['allocation_type'],
                'project_id'      => $validated['project_id'] ?? null,
                'award_id'        => $validated['award_id'] ?? null,
                'allocated_amount' => $validated['allocated_amount'],
                'spent_amount'    => 0,
                'period'          => $validated['period'],
                'description'     => $validated['description'] ?? null,
                'fiscal_year'     => $validated['fiscal_year'],
                'status'          => 'approved',
                'created_by'      => auth()->id(),
                'approved_by'     => auth()->id(),
                'approved_at'     => now(),
            ]);

            $budget->load([
                'department:department_id,name',
                'project:project_id,project_name,tender_id,budget,project_status',
                'project.tender:tender_id,title,tender_number',
                'awardedTender:award_id,tender_id,user_id,awarded_document',
                'awardedTender.tender:tender_id,title,tender_number,procurement_entity',
                'creator:user_id,name'
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Budget allocated and approved successfully',
                'data'    => $this->normalizeBudgetTarget($budget)
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to allocate budget: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending budgets for CEO approval
     */
    public function getPendingBudgets(Request $request)
    {
        try {
            $fiscalYear = $request->get('fiscal_year', date('Y'));
            
            $pendingBudgets = BudgetAllocation::where('fiscal_year', $fiscalYear)
                ->where('status', 'pending')
                ->with([
                    'department:department_id,name',
                    'user:user_id,name,department_id',
                    'project:project_id,project_name,tender_id,budget,project_status',
                    'project.tender:tender_id,title,tender_number',
                    'awardedTender:award_id,tender_id,user_id,awarded_document',
                    'awardedTender.tender:tender_id,title,tender_number,procurement_entity',
                    'creator:user_id,name'
                ])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($budget) {
                    $normalized = $this->normalizeBudgetTarget($budget);

                    return [
                        'id' => $budget->id,
                        'allocation_type' => $normalized['type'],
                        'type_label' => $normalized['type_label'],
                        'target_label' => $normalized['target_label'],
                        'target_meta' => $normalized['target_meta'],
                        'department_id' => $normalized['department_id'],
                        'project_id' => $normalized['project_id'],
                        'award_id' => $normalized['award_id'],
                        'allocated_amount' => (float) ($budget->allocated_amount ?? 0),
                        'spent_amount' => (float) ($budget->spent_amount ?? 0),
                        'period' => $budget->period,
                        'description' => $budget->description,
                        'fiscal_year' => $budget->fiscal_year,
                        'status' => $budget->status,
                        'created_at' => $budget->created_at,
                        'approved_at' => $budget->approved_at,
                        'award_document' => $normalized['award_document'],
                        'department' => [
                            'department_id' => $normalized['department_id'],
                            'name' => $normalized['target_label'],
                        ],
                        'creator' => [
                            'user_id' => $budget->creator?->user_id,
                            'name' => $budget->creator?->name ?? 'Unknown User',
                        ],
                    ];
                })
                ->values();
            
            return response()->json([
                'status' => 'success',
                'data' => $pendingBudgets
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch pending budgets: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Approve budget (CEO role)
     */
    public function approveBudget(Request $request, $budgetId)
    {
        try {
            $budget = BudgetAllocation::findOrFail($budgetId);
            
            if ($budget->status !== 'pending') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Budget is not in pending status'
                ], 400);
            }
            
            $budget->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now()
            ]);
            
            // Load relationships for response
            $budget->load(['department:department_id,name', 'creator:user_id,name', 'approver:user_id,name']);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Budget approved successfully',
                'data' => $budget
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to approve budget: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getBudgetSources()
    {
        try {
            $departments = Department::query()
                ->orderBy('name')
                ->get(['department_id', 'name'])
                ->map(function ($department) {
                    return [
                        'id' => $department->department_id,
                        'type' => BudgetAllocation::TYPE_DEPARTMENT,
                        'label' => $department->name,
                        'subtitle' => 'Department',
                    ];
                });

            $projects = Project::with('tender:tender_id,title,tender_number')
                ->orderBy('project_name')
                ->get()
                ->map(function ($project) {
                    return [
                        'id' => $project->project_id,
                        'type' => BudgetAllocation::TYPE_PROJECT,
                        'label' => $project->project_name,
                        'subtitle' => collect([
                            $project->tender?->title,
                            $project->project_status,
                        ])->filter()->implode(' • '),
                    ];
                });

            $awardedTenders = AwardedTender::with([
                'user:user_id,name',
                'tender:tender_id,title,tender_number,procurement_entity',
            ])
                ->orderByDesc('award_id')
                ->get()
                ->map(function ($award) {
                    return [
                        'id' => $award->award_id,
                        'type' => BudgetAllocation::TYPE_AWARDED_TENDER,
                        'label' => $award->tender?->title ?? 'Unknown Tender',
                        'subtitle' => collect([
                            $award->tender?->tender_number,
                            $award->user?->name,
                            $award->tender?->procurement_entity,
                        ])->filter()->implode(' • '),
                        'award_document' => $award->awarded_document,
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'departments' => $departments->values(),
                    'projects' => $projects->values(),
                    'awarded_tenders' => $awardedTenders->values(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch budget sources: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Reject budget (CEO role)
     */
    public function rejectBudget(Request $request, $budgetId)
    {
        try {
            $request->validate([
                'rejection_reason' => 'required|string'
            ]);
            
            $budget = BudgetAllocation::findOrFail($budgetId);
            
            if ($budget->status !== 'pending') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Budget is not in pending status'
                ], 400);
            }
            
            $budget->update([
                'status' => 'rejected',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'rejection_reason' => $request->rejection_reason
            ]);
            
            // Load relationships for response
            $budget->load(['department:department_id,name', 'creator:user_id,name', 'approver:user_id,name']);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Budget rejected successfully',
                'data' => $budget
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reject budget: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get budgets created by accountant
     */
    public function getMyBudgets(Request $request)
    {
        try {
            $fiscalYear = $request->get('fiscal_year', date('Y'));
            
            $budgets = BudgetAllocation::where('fiscal_year', $fiscalYear)
                ->where('created_by', auth()->id())
                ->with(['department:department_id,name', 'approver:user_id,name'])
                ->orderBy('created_at', 'desc')
                ->get();
            
            return response()->json([
                'status' => 'success',
                'data' => $budgets
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch budgets: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get budget transactions
     */
    public function getBudgetTransactions(Request $request)
    {
        try {
            $departmentId = $request->get('department_id');
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');
            $limit = $request->get('limit', 50);
            
            // Mock transaction data - in real implementation, this would come from budget_transactions table
            $transactions = [
                [
                    'id' => 1,
                    'description' => 'Office Rent Payment',
                    'category' => 'Facilities',
                    'amount' => 2500000,
                    'type' => 'expense',
                    'department' => 'Operations',
                    'date' => '2024-01-15',
                    'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
                    'icon_bg' => 'bg-red-100 dark:bg-red-900/30',
                    'icon_color' => 'text-red-600 dark:text-red-400',
                    'amount_color' => 'text-red-600'
                ],
                [
                    'id' => 2,
                    'description' => 'Software License Renewal',
                    'category' => 'Technology',
                    'amount' => 850000,
                    'type' => 'expense',
                    'department' => 'Technology',
                    'date' => '2024-01-14',
                    'icon' => 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
                    'icon_bg' => 'bg-blue-100 dark:bg-blue-900/30',
                    'icon_color' => 'text-blue-600 dark:text-blue-400',
                    'amount_color' => 'text-red-600'
                ],
                [
                    'id' => 3,
                    'description' => 'Client Payment Received',
                    'category' => 'Revenue',
                    'amount' => 5000000,
                    'type' => 'income',
                    'department' => 'Sales',
                    'date' => '2024-01-13',
                    'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                    'icon_bg' => 'bg-green-100 dark:bg-green-900/30',
                    'icon_color' => 'text-green-600 dark:text-green-400',
                    'amount_color' => 'text-green-600'
                ]
            ];
            
            return response()->json([
                'status' => 'success',
                'data' => $transactions
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch transactions: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get variance analysis
     */
    public function getVarianceAnalysis(Request $request)
    {
        try {
            $period = $request->get('period', 'monthly');
            $fiscalYear = $request->get('fiscal_year', date('Y'));
            
            // Mock variance data - in real implementation, this would compare budgeted vs actual
            $varianceData = [
                [
                    'department' => 'Operations',
                    'budgeted' => 15000000,
                    'actual' => 11700000,
                    'variance' => -3300000,
                    'variance_percentage' => 22,
                    'variance_color' => 'text-green-600',
                    'bar_color' => 'bg-green-500'
                ],
                [
                    'department' => 'Projects',
                    'budgeted' => 20000000,
                    'actual' => 18500000,
                    'variance' => -1500000,
                    'variance_percentage' => 7.5,
                    'variance_color' => 'text-green-600',
                    'bar_color' => 'bg-green-500'
                ],
                [
                    'department' => 'Marketing',
                    'budgeted' => 5000000,
                    'actual' => 3200000,
                    'variance' => -1800000,
                    'variance_percentage' => 36,
                    'variance_color' => 'text-green-600',
                    'bar_color' => 'bg-green-500'
                ],
                [
                    'department' => 'Technology',
                    'budgeted' => 8000000,
                    'actual' => 7200000,
                    'variance' => -800000,
                    'variance_percentage' => 10,
                    'variance_color' => 'text-green-600',
                    'bar_color' => 'bg-green-500'
                ]
            ];
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'variances' => $varianceData,
                    'period' => $period,
                    'fiscal_year' => $fiscalYear
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch variance analysis: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get budget alerts
     */
    public function getBudgetAlerts(Request $request)
    {
        try {
            // Mock alert data - in real implementation, this would check budget thresholds
            $alerts = [
                [
                    'id' => 1,
                    'title' => 'Projects Budget Near Limit',
                    'description' => 'Projects department has utilized 92% of allocated budget',
                    'time' => '2 hours ago',
                    'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z',
                    'icon_bg' => 'bg-amber-100 dark:bg-amber-900/30',
                    'icon_color' => 'text-amber-600 dark:text-amber-400',
                    'border_color' => 'border-amber-500',
                    'severity' => 'warning'
                ],
                [
                    'id' => 2,
                    'title' => 'Marketing Budget Underutilized',
                    'description' => 'Marketing department has only used 64% of allocated budget',
                    'time' => '1 day ago',
                    'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                    'icon_bg' => 'bg-blue-100 dark:bg-blue-900/30',
                    'icon_color' => 'text-blue-600 dark:text-blue-400',
                    'border_color' => 'border-blue-500',
                    'severity' => 'info'
                ]
            ];
            
            return response()->json([
                'status' => 'success',
                'data' => $alerts
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch budget alerts: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Generate budget report
     */
    public function generateBudgetReport(Request $request)
    {
        try {
            $reportType = $request->get('type', 'summary');
            $fiscalYear = $request->get('fiscal_year', date('Y'));
            $format = $request->get('format', 'pdf');
            $departmentId = $request->get('department_id');
            
            // Generate report data
            $reportData = $this->generateReportData($reportType, $fiscalYear, $departmentId);
            
            $reportId = 'BUD_RPT_' . strtoupper(uniqid());
            
            // In real implementation, this would generate actual files
            return response()->json([
                'status' => 'success',
                'data' => [
                    'report_id' => $reportId,
                    'type' => $reportType,
                    'format' => $format,
                    'fiscal_year' => $fiscalYear,
                    'generated_at' => now()->toISOString(),
                    'download_url' => "/api/budget/reports/download/{$reportId}",
                    'data' => $reportData
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate budget report: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Helper method to generate report data
     */
    private function generateReportData($type, $fiscalYear, $departmentId = null)
    {
        switch ($type) {
            case 'summary':
                return [
                    'title' => 'Budget Summary Report',
                    'fiscal_year' => $fiscalYear,
                    'sections' => [
                        'Executive Summary',
                        'Department Overview',
                        'Budget Utilization',
                        'Variance Analysis',
                        'Recommendations'
                    ]
                ];
            case 'detailed':
                return [
                    'title' => 'Detailed Budget Report',
                    'fiscal_year' => $fiscalYear,
                    'sections' => [
                        'Budget Allocation',
                        'Expenditure Analysis',
                        'Transaction History',
                        'Performance Metrics',
                        'Forecast Analysis'
                    ]
                ];
            case 'variance':
                return [
                    'title' => 'Budget Variance Report',
                    'fiscal_year' => $fiscalYear,
                    'sections' => [
                        'Variance Summary',
                        'Department Breakdown',
                        'Trend Analysis',
                        'Root Cause Analysis',
                        'Corrective Actions'
                    ]
                ];
            default:
                return [
                    'title' => 'Custom Budget Report',
                    'fiscal_year' => $fiscalYear,
                    'sections' => []
                ];
        }
    }

    /**
     * Get budget reductions for CEO dashboard
     */
    public function getBudgetReductions(Request $request)
    {
        try {
            $fiscalYear = $request->get('fiscal_year', date('Y'));
            
            // Get projects with budgets for the fiscal year
            try {
                $projects = DB::table('projects')
                    ->select('project_name', 'budget', 'updated_at')
                    ->whereNotNull('budget')
                    ->whereYear('created_at', $fiscalYear)
                    ->get();
            } catch (\Exception $e) {
                // If project_name column doesn't exist, try other common column names
                try {
                    $projects = DB::table('projects')
                        ->select('title', 'budget', 'updated_at')
                        ->whereNotNull('budget')
                        ->whereYear('created_at', $fiscalYear)
                        ->get();
                } catch (\Exception $e2) {
                    // Fallback to just budget and updated_at
                    $projects = DB::table('projects')
                        ->select('budget', 'updated_at')
                        ->whereNotNull('budget')
                        ->whereYear('created_at', $fiscalYear)
                        ->get();
                }
            }

            // Try to get original_budget if column exists, otherwise use mock data
            try {
                $projectsWithOriginal = DB::table('projects')
                    ->select('project_name', 'budget', 'original_budget', 'updated_at')
                    ->whereNotNull('budget')
                    ->whereNotNull('original_budget')
                    ->whereYear('created_at', $fiscalYear)
                    ->get();
            } catch (\Exception $e) {
                // If original_budget column doesn't exist, use mock data
                $projectsWithOriginal = collect([]);
            }

            // Calculate reductions from projects that had their budget reduced
            $reducedProjects = $projectsWithOriginal->filter(function($project) {
                return $project->original_budget && $project->original_budget > $project->budget;
            });

            $totalOriginalBudget = $reducedProjects->sum('original_budget');
            $totalCurrentBudget = $projects->sum('budget');
            $totalReduced = $reducedProjects->sum(function($project) {
                return $project->original_budget - $project->budget;
            });

            // Calculate overall reduction percentage
            $overallReductionPercentage = $totalOriginalBudget > 0 
                ? round(($totalReduced / $totalOriginalBudget) * 100, 2) 
                : 0;

            // Get recent reduction activities
            $recentReductions = $reducedProjects
                ->sortByDesc('updated_at')
                ->take(10)
                ->map(function($project) {
                    $reduction = $project->original_budget - $project->budget;
                    return [
                        'project_name' => $project->project_name ?? 'Unknown Project',
                        'original_budget' => $project->original_budget,
                        'current_budget' => $project->budget,
                        'reduction_amount' => $reduction,
                        'reduction_percentage' => $project->original_budget > 0 
                            ? round(($reduction / $project->original_budget) * 100, 2) 
                            : 0,
                        'date' => $project->updated_at
                    ];
                });

            return response()->json([
                'status' => true,
                'data' => [
                    'total_original_budget' => $totalOriginalBudget,
                    'total_reduced_budget' => $totalReduced,
                    'total_current_budget' => $totalCurrentBudget,
                    'overall_reduction_percentage' => $overallReductionPercentage,
                    'projects_count' => $reducedProjects->count(),
                    'recent_reductions' => $recentReductions,
                    'budget_reduction_trend' => $recentReductions->map(function($item) {
                        return [
                            'date' => $item['date'],
                            'amount' => $item['reduction_amount']
                        ];
                    })
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch budget reductions: ' . $e->getMessage()
            ], 500);
        }
    }
}
