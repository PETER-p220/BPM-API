<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Analysis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BudgetController extends Controller
{
    /**
     * Get budget reduction statistics for CEO dashboard
     */
    public function getBudgetReductions()
    {
        try {
            // Get projects with approved analyses and calculate budget reductions
            $projects = Project::with(['analyses' => function($query) {
                $query->where('status', 'approved');
            }])->get();

            $totalOriginalBudget = 0;
            $totalReducedBudget = 0;
            $budgetReductions = [];

            foreach ($projects as $project) {
                $originalBudget = $project->budget ?? 0;
                $totalOriginalBudget += $originalBudget;

                // Calculate total from approved analyses
                $approvedAnalysisTotal = $project->analyses->sum('total_amount_needed');
                
                // Calculate reduction amount
                $reductionAmount = $originalBudget - $approvedAnalysisTotal;
                
                if ($reductionAmount > 0) {
                    $totalReducedBudget += $reductionAmount;
                    
                    $budgetReductions[] = [
                        'project_id' => $project->project_id,
                        'project_name' => $project->project_name,
                        'original_budget' => $originalBudget,
                        'approved_analysis_total' => $approvedAnalysisTotal,
                        'reduction_amount' => $reductionAmount,
                        'reduction_percentage' => $originalBudget > 0 ? ($reductionAmount / $originalBudget) * 100 : 0,
                        'analyses_count' => $project->analyses->count(),
                        'last_updated' => $project->updated_at
                    ];
                }
            }

            return response()->json([
                'status' => 200,
                'data' => [
                    'total_original_budget' => $totalOriginalBudget,
                    'total_reduced_budget' => $totalReducedBudget,
                    'total_current_budget' => $totalOriginalBudget - $totalReducedBudget,
                    'overall_reduction_percentage' => $totalOriginalBudget > 0 ? ($totalReducedBudget / $totalOriginalBudget) * 100 : 0,
                    'projects_count' => count($budgetReductions),
                    'recent_reductions' => array_slice($budgetReductions, 0, 5), // Last 5 reductions
                    'budget_reduction_trend' => $this->getBudgetReductionTrend()
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching budget reductions', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Error fetching budget reduction data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get budget overview including reductions from approved analyses
     */
    public function getBudgetOverview(Request $request)
    {
        try {
            $fiscalYear = $request->input('fiscal_year', date('Y'));
            
            // Get all projects for the fiscal year
            $projects = Project::with(['analyses' => function($query) {
                $query->where('status', 'approved');
            }])->whereYear('created_at', $fiscalYear)->get();

            $totalAllocated = 0;
            $totalSpent = 0;
            $totalReduced = 0;

            foreach ($projects as $project) {
                $originalBudget = $project->budget ?? 0;
                $totalAllocated += $originalBudget;
                
                // Calculate total from approved analyses
                $approvedAnalysisTotal = $project->analyses->sum('total_amount_needed');
                
                // Calculate reduction amount
                $reductionAmount = $originalBudget - $approvedAnalysisTotal;
                $totalReduced += $reductionAmount;
                
                // Spent amount is the approved analysis total
                $totalSpent += $approvedAnalysisTotal;
            }

            $currentBudget = $totalAllocated - $totalReduced;
            $utilizationPercentage = $totalAllocated > 0 ? ($totalSpent / $totalAllocated) * 100 : 0;

            return response()->json([
                'status' => 200,
                'data' => [
                    'total_allocated' => $totalAllocated,
                    'total_spent' => $totalSpent,
                    'total_remaining' => $currentBudget,
                    'overall_utilization' => round($utilizationPercentage, 2),
                    'trend_data' => $this->getBudgetReductionTrend($fiscalYear)
                ]
                ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching budget overview', [
                'error' => $e->getMessage(),
                'fiscal_year' => $request->input('fiscal_year')
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Error fetching budget overview',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get budget reduction trend over time
     */
    private function getBudgetReductionTrend()
    {
        try {
            // Get monthly trend for the last 6 months
            $trend = [];
            $currentDate = now();

            for ($i = 5; $i >= 0; $i--) {
                $monthStart = $currentDate->copy()->subMonths($i)->startOfMonth();
                $monthEnd = $currentDate->copy()->subMonths($i)->endOfMonth();

                $monthlyReductions = Project::with(['analyses' => function($query) use ($monthStart, $monthEnd) {
                    $query->where('status', 'approved')
                          ->whereBetween('updated_at', [$monthStart, $monthEnd]);
                }])
                ->get()
                ->map(function ($project) {
                    $originalBudget = $project->budget ?? 0;
                    $approvedAnalysisTotal = $project->analyses->sum('total_amount_needed');
                    $reductionAmount = $originalBudget - $approvedAnalysisTotal;
                    
                    return $reductionAmount > 0 ? $reductionAmount : 0;
                })
                ->sum();

                $trend[] = [
                    'month' => $monthStart->format('M Y'),
                    'reduction_amount' => $monthlyReductions,
                    'projects_affected' => Project::whereHas('analyses', function($query) use ($monthStart, $monthEnd) {
                        $query->where('status', 'approved')
                              ->whereBetween('updated_at', [$monthStart, $monthEnd]);
                    })->count()
                ];
            }

            return $trend;

        } catch (\Exception $e) {
            Log::error('Error calculating budget reduction trend', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get detailed budget reduction for a specific project
     */
    public function getProjectBudgetReduction($projectId)
    {
        try {
            $project = Project::with(['analyses' => function($query) {
                $query->where('status', 'approved');
            }])->find($projectId);

            if (!$project) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Project not found'
                ], 404);
            }

            $originalBudget = $project->budget ?? 0;
            $approvedAnalysisTotal = $project->analyses->sum('total_amount_needed');
            $reductionAmount = $originalBudget - $approvedAnalysisTotal;

            return response()->json([
                'status' => 200,
                'data' => [
                    'project_id' => $project->project_id,
                    'project_name' => $project->project_name,
                    'original_budget' => $originalBudget,
                    'approved_analysis_total' => $approvedAnalysisTotal,
                    'reduction_amount' => $reductionAmount,
                    'reduction_percentage' => $originalBudget > 0 ? ($reductionAmount / $originalBudget) * 100 : 0,
                    'analyses_count' => $project->analyses->count(),
                    'analyses' => $project->analyses->map(function ($analysis) {
                        return [
                            'analysis_id' => $analysis->analysis_id,
                            'item_description' => $analysis->item_description,
                            'total_amount_needed' => $analysis->total_amount_needed,
                            'status' => $analysis->status,
                            'updated_at' => $analysis->updated_at
                        ];
                    })
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching project budget reduction', [
                'error' => $e->getMessage(),
                'project_id' => $projectId
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Error fetching project budget reduction',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
