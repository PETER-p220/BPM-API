<?php

namespace App\Http\Controllers;

use App\Models\PerformanceEvaluation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Exception;

class PerformanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get all performance evaluations
     */
    public function index(Request $request)
    {
        try {
            $query = PerformanceEvaluation::with([
                'employee:user_id,name,email,department_id',
                'reviewer:user_id,name,email',
                'employee.department'
            ]);

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('department')) {
                $query->whereHas('employee', function($q) use ($request) {
                    $q->where('department_id', $request->department);
                });
            }

            if ($request->has('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }

            if ($request->has('review_period')) {
                $query->where('review_period', 'like', '%' . $request->review_period . '%');
            }

            $evaluations = $query->orderBy('review_date', 'desc')->get();

            return response()->json([
                'status' => true,
                'message' => 'Performance evaluations fetched successfully.',
                'data' => $evaluations
            ], 200);
        } catch (Exception $e) {
            \Log::error('Error fetching performance evaluations: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch performance evaluations.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific performance evaluation
     */
    public function show($id)
    {
        try {
            $evaluation = PerformanceEvaluation::with([
                'employee:user_id,name,email,department_id',
                'reviewer:user_id,name,email',
                'employee.department'
            ])->findOrFail($id);

            return response()->json([
                'status' => true,
                'message' => 'Performance evaluation fetched successfully.',
                'data' => $evaluation
            ], 200);
        } catch (Exception $e) {
            \Log::error('Error fetching performance evaluation: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Performance evaluation not found.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Create a new performance evaluation
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:users,user_id',
            'review_period' => 'required|string|max:255',
            'job_knowledge' => 'required|integer|min:1|max:5',
            'work_quality' => 'required|integer|min:1|max:5',
            'productivity' => 'required|integer|min:1|max:5',
            'communication' => 'required|integer|min:1|max:5',
            'teamwork' => 'required|integer|min:1|max:5',
            'initiative' => 'required|integer|min:1|max:5',
            'overall_rating' => 'required|integer|min:1|max:5',
            'overall_comments' => 'required|string|min:10',
            'review_date' => 'required|date',
            'goals_next_period' => 'nullable|string',
            'job_knowledge_comments' => 'nullable|string',
            'work_quality_comments' => 'nullable|string',
            'productivity_comments' => 'nullable|string',
            'communication_comments' => 'nullable|string',
            'teamwork_comments' => 'nullable|string',
            'initiative_comments' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors.',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            // Check if evaluation already exists for this employee and period
            $existingEvaluation = PerformanceEvaluation::where('employee_id', $request->employee_id)
                ->where('review_period', $request->review_period)
                ->first();

            if ($existingEvaluation) {
                return response()->json([
                    'status' => false,
                    'message' => 'Performance evaluation already exists for this employee and review period.'
                ], 400);
            }

            // Calculate status based on overall rating
            $status = $this->calculateStatus($request->overall_rating);

            $evaluation = PerformanceEvaluation::create([
                'employee_id' => $request->employee_id,
                'reviewer_id' => Auth::id(),
                'review_period' => $request->review_period,
                'job_knowledge' => $request->job_knowledge,
                'work_quality' => $request->work_quality,
                'productivity' => $request->productivity,
                'communication' => $request->communication,
                'teamwork' => $request->teamwork,
                'initiative' => $request->initiative,
                'overall_rating' => $request->overall_rating,
                'overall_comments' => $request->overall_comments,
                'review_date' => $request->review_date,
                'goals_next_period' => $request->goals_next_period,
                'job_knowledge_comments' => $request->job_knowledge_comments,
                'work_quality_comments' => $request->work_quality_comments,
                'productivity_comments' => $request->productivity_comments,
                'communication_comments' => $request->communication_comments,
                'teamwork_comments' => $request->teamwork_comments,
                'initiative_comments' => $request->initiative_comments,
                'status' => $status,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Performance evaluation created successfully.',
                'data' => $evaluation->load(['employee:user_id,name,email,department_id', 'reviewer:user_id,name', 'employee.department'])
            ], 201);
        } catch (Exception $e) {
            \Log::error('Error creating performance evaluation: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to create performance evaluation.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a performance evaluation
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'review_period' => 'sometimes|string|max:255',
            'job_knowledge' => 'sometimes|integer|min:1|max:5',
            'work_quality' => 'sometimes|integer|min:1|max:5',
            'productivity' => 'sometimes|integer|min:1|max:5',
            'communication' => 'sometimes|integer|min:1|max:5',
            'teamwork' => 'sometimes|integer|min:1|max:5',
            'initiative' => 'sometimes|integer|min:1|max:5',
            'overall_rating' => 'sometimes|integer|min:1|max:5',
            'overall_comments' => 'sometimes|string|min:10',
            'review_date' => 'sometimes|date',
            'goals_next_period' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors.',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $evaluation = PerformanceEvaluation::findOrFail($id);

            // Update status if overall rating changed
            if ($request->has('overall_rating')) {
                $status = $this->calculateStatus($request->overall_rating);
                $request->merge(['status' => $status]);
            }

            $evaluation->update($request->all());

            return response()->json([
                'status' => true,
                'message' => 'Performance evaluation updated successfully.',
                'data' => $evaluation->fresh()->load(['employee:id,name,email,department_id', 'reviewer:id,name', 'employee.department'])
            ], 200);
        } catch (Exception $e) {
            \Log::error('Error updating performance evaluation: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to update performance evaluation.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a performance evaluation
     */
    public function destroy($id)
    {
        try {
            $evaluation = PerformanceEvaluation::findOrFail($id);
            $evaluation->delete();

            return response()->json([
                'status' => true,
                'message' => 'Performance evaluation deleted successfully.'
            ], 200);
        } catch (Exception $e) {
            \Log::error('Error deleting performance evaluation: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete performance evaluation.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get performance statistics
     */
    public function getStatistics(Request $request)
    {
        try {
            $query = PerformanceEvaluation::query();

            // Apply date range filter
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('review_date', [$request->start_date, $request->end_date]);
            }

            $totalEvaluations = $query->count();
            $outstanding = $query->where('overall_rating', '>=', 4.5)->count();
            $exceedsExpectations = $query->where('overall_rating', '>=', 3.5)->where('overall_rating', '<', 4.5)->count();
            $meetsExpectations = $query->where('overall_rating', '>=', 2.5)->where('overall_rating', '<', 3.5)->count();
            $needsImprovement = $query->where('overall_rating', '<', 2.5)->count();

            // Department-wise statistics
            $departmentStats = PerformanceEvaluation::join('users', 'performance_evaluations.employee_id', '=', 'users.user_id')
                ->leftJoin('departments', 'users.department_id', '=', 'departments.department_id')
                ->selectRaw('
                    departments.name as department,
                    COUNT(*) as total,
                    AVG(performance_evaluations.overall_rating) as average_rating,
                    SUM(CASE WHEN performance_evaluations.overall_rating >= 4 THEN 1 ELSE 0 END) as high_performers
                ')
                ->groupBy('departments.name')
                ->orderBy('average_rating', 'desc')
                ->get();

            // Trend data (last 6 months)
            $trendData = PerformanceEvaluation::selectRaw('
                DATE_FORMAT(review_date, "%Y-%m") as period,
                COUNT(*) as evaluations,
                AVG(overall_rating) as average_rating
            ')
            ->where('review_date', '>=', now()->subMonths(6))
            ->groupBy('period')
            ->orderBy('period')
            ->get();

            return response()->json([
                'status' => true,
                'message' => 'Performance statistics fetched successfully.',
                'data' => [
                    'total_evaluations' => $totalEvaluations,
                    'outstanding' => $outstanding,
                    'exceeds_expectations' => $exceedsExpectations,
                    'meets_expectations' => $meetsExpectations,
                    'needs_improvement' => $needsImprovement,
                    'department_stats' => $departmentStats,
                    'trend_data' => $trendData
                ]
            ], 200);
        } catch (Exception $e) {
            \Log::error('Error fetching performance statistics: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch performance statistics.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employee performance history
     */
    public function getEmployeePerformance($employeeId)
    {
        try {
            $evaluations = PerformanceEvaluation::where('employee_id', $employeeId)
                ->with('reviewer:id,name')
                ->orderBy('review_date', 'desc')
                ->get();

            // Calculate performance trends
            $trend = $evaluations->map(function($evaluation) {
                return [
                    'period' => $evaluation->review_period,
                    'rating' => $evaluation->overall_rating,
                    'date' => $evaluation->review_date
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Employee performance history fetched successfully.',
                'data' => [
                    'evaluations' => $evaluations,
                    'trend' => $trend,
                    'average_rating' => $evaluations->avg('overall_rating'),
                    'total_evaluations' => $evaluations->count()
                ]
            ], 200);
        } catch (Exception $e) {
            \Log::error('Error fetching employee performance: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch employee performance.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate status based on overall rating
     */
    private function calculateStatus($rating)
    {
        if ($rating >= 4.5) {
            return 'outstanding';
        } elseif ($rating >= 3.5) {
            return 'exceeds_expectations';
        } elseif ($rating >= 2.5) {
            return 'meets_expectations';
        } elseif ($rating >= 1.5) {
            return 'needs_improvement';
        } else {
            return 'unsatisfactory';
        }
    }

    /**
     * Get all performance evaluations for CEO
     */
    public function ceoIndex(Request $request)
    {
        try {
            $user = Auth::user();
            \Log::info('CEO Performance API called by user: ' . $user->user_id . ' with role: ' . $user->role_id);
            
            // Check if user is CEO (role_id 7)
            if ($user->role_id != 7) {
                \Log::warning('User ' . $user->user_id . ' with role ' . $user->role_id . ' tried to access CEO endpoint');
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized. CEO access required.'
                ], 403);
            }

            $query = PerformanceEvaluation::with([
                'employee:user_id,name,email,department_id',
                'reviewer:user_id,name,email',
                'employee.department'
            ]);

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('department')) {
                $query->whereHas('employee', function($q) use ($request) {
                    $q->where('department_id', $request->department);
                });
            }

            if ($request->has('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }

            if ($request->has('review_period')) {
                $query->where('review_period', 'like', '%' . $request->review_period . '%');
            }

            $evaluations = $query->orderBy('review_date', 'desc')->get();
            
            \Log::info('CEO found ' . $evaluations->count() . ' performance evaluations');

            return response()->json([
                'status' => true,
                'message' => 'CEO performance evaluations fetched successfully.',
                'data' => $evaluations
            ], 200);
        } catch (Exception $e) {
            \Log::error('Error fetching CEO performance evaluations: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch CEO performance evaluations.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all performance evaluations for Admin
     */
    public function adminIndex(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Check if user is Admin (role_id 2)
            if ($user->role_id != 2) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $query = PerformanceEvaluation::with([
                'employee:user_id,name,email,department_id',
                'reviewer:user_id,name,email',
                'employee.department'
            ]);

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('department')) {
                $query->whereHas('employee', function($q) use ($request) {
                    $q->where('department_id', $request->department);
                });
            }

            if ($request->has('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }

            if ($request->has('review_period')) {
                $query->where('review_period', 'like', '%' . $request->review_period . '%');
            }

            $evaluations = $query->orderBy('review_date', 'desc')->get();

            return response()->json([
                'status' => true,
                'message' => 'Admin performance evaluations fetched successfully.',
                'data' => $evaluations
            ], 200);
        } catch (Exception $e) {
            \Log::error('Error fetching Admin performance evaluations: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch Admin performance evaluations.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Auto-calculate performance based on user's work done and updates
     */
    public function autoCalculatePerformance(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_id' => 'required|exists:users,user_id',
                'review_period' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation errors.',
                    'errors' => $validator->errors()
                ], 400);
            }

            $employeeId = $request->employee_id;
            $reviewPeriod = $request->review_period;

            // Get user's projects and updates for the review period
            $projectsCompleted = \App\Models\Project::where('assigned_to', $employeeId)
                ->where('status', 'completed')
                ->whereBetween('updated_at', [$this->getPeriodStart($reviewPeriod), $this->getPeriodEnd($reviewPeriod)])
                ->count();

            $updatesCount = \App\Models\Update::where('user_id', $employeeId)
                ->whereBetween('created_at', [$this->getPeriodStart($reviewPeriod), $this->getPeriodEnd($reviewPeriod)])
                ->count();

            // Calculate ratings based on performance metrics
            $jobKnowledge = $this->calculateRating($projectsCompleted, $updatesCount, 'job_knowledge');
            $workQuality = $this->calculateRating($projectsCompleted, $updatesCount, 'work_quality');
            $productivity = $this->calculateRating($projectsCompleted, $updatesCount, 'productivity');
            $communication = $this->calculateRating($projectsCompleted, $updatesCount, 'communication');
            $teamwork = $this->calculateRating($projectsCompleted, $updatesCount, 'teamwork');
            $initiative = $this->calculateRating($projectsCompleted, $updatesCount, 'initiative');

            $overallRating = ($jobKnowledge + $workQuality + $productivity + $communication + $teamwork + $initiative) / 6;

            // Check if evaluation already exists
            $existingEvaluation = PerformanceEvaluation::where('employee_id', $employeeId)
                ->where('review_period', $reviewPeriod)
                ->first();

            if ($existingEvaluation) {
                return response()->json([
                    'status' => false,
                    'message' => 'Performance evaluation already exists for this employee and review period.'
                ], 400);
            }

            // Create the evaluation
            $status = $this->calculateStatus($overallRating);

            $evaluation = PerformanceEvaluation::create([
                'employee_id' => $employeeId,
                'reviewer_id' => Auth::id(),
                'review_period' => $reviewPeriod,
                'job_knowledge' => $jobKnowledge,
                'work_quality' => $workQuality,
                'productivity' => $productivity,
                'communication' => $communication,
                'teamwork' => $teamwork,
                'initiative' => $initiative,
                'overall_rating' => round($overallRating),
                'overall_comments' => "Auto-calculated performance based on $projectsCompleted completed projects and $updatesCount updates during $reviewPeriod.",
                'review_date' => now(),
                'goals_next_period' => "Continue maintaining productivity and aim for higher project completion rate.",
                'status' => $status,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Performance evaluation auto-calculated successfully.',
                'data' => $evaluation->load(['employee:user_id,name,email,department_id', 'reviewer:user_id,name', 'employee.department'])
            ], 201);

        } catch (Exception $e) {
            \Log::error('Error auto-calculating performance: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to auto-calculate performance evaluation.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate individual rating based on metrics
     */
    private function calculateRating($projectsCompleted, $updatesCount, $criteria)
    {
        // Base calculation logic - can be refined based on specific criteria
        $baseScore = min(5, max(1, ($projectsCompleted * 0.6) + ($updatesCount * 0.1)));

        // Adjust based on criteria
        switch ($criteria) {
            case 'job_knowledge':
                return min(5, $baseScore + ($projectsCompleted > 0 ? 0.5 : 0));
            case 'work_quality':
                return min(5, $baseScore + ($projectsCompleted > 2 ? 0.5 : 0));
            case 'productivity':
                return min(5, $baseScore + 1);
            case 'communication':
                return min(5, $baseScore + ($updatesCount > 5 ? 0.5 : 0));
            case 'teamwork':
                return min(5, $baseScore + 0.3);
            case 'initiative':
                return min(5, $baseScore + ($projectsCompleted > 1 ? 0.3 : 0));
            default:
                return $baseScore;
        }
    }

    /**
     * Get period start date from review period string
     */
    private function getPeriodStart($reviewPeriod)
    {
        // Assuming format like "2024-Q1", "2024-01", etc.
        if (strpos($reviewPeriod, '-Q') !== false) {
            $parts = explode('-Q', $reviewPeriod);
            $year = $parts[0];
            $quarter = $parts[1];
            $month = (($quarter - 1) * 3) + 1;
            return "$year-$month-01";
        } elseif (strpos($reviewPeriod, '-') !== false) {
            $parts = explode('-', $reviewPeriod);
            $year = $parts[0];
            $month = $parts[1];
            return "$year-$month-01";
        }
        
        // Default to current month start
        return now()->startOfMonth()->format('Y-m-d');
    }

    /**
     * Get period end date from review period string
     */
    private function getPeriodEnd($reviewPeriod)
    {
        // Assuming format like "2024-Q1", "2024-01", etc.
        if (strpos($reviewPeriod, '-Q') !== false) {
            $parts = explode('-Q', $reviewPeriod);
            $year = $parts[0];
            $quarter = $parts[1];
            $month = ($quarter * 3);
            return "$year-$month-31";
        } elseif (strpos($reviewPeriod, '-') !== false) {
            $parts = explode('-', $reviewPeriod);
            $year = $parts[0];
            $month = $parts[1];
            return "$year-$month-31";
        }
        
        // Default to current month end
        return now()->endOfMonth()->format('Y-m-d');
    }
}
