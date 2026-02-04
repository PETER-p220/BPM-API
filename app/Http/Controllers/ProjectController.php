<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use App\Models\Contract;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class ProjectController extends Controller
{
    public function index(Request $request)
{
    try {
        $projects = Project::with([
            'user:user_id,name',
            'contract:contract_id,title,time_line_category,start_date,end_date,pdf_file,status,performance_guarantee',
            'tender:tender_id,tender_type,attachment'
        ])
        ->get([
            'project_id',
            'project_name',
            'tender_id',
            'user_id',
            'contract_id',
            'member_id',
            'created_by',
            'project_status',
            'start_date',
            'end_date',
            'extended_date',
            'follow_up',
            'created_at',
            'updated_at'
        ]);
        // Process member_id to fetch user names
        $projects = $projects->map(function ($project) {
            if ($project->member_id) {
                // Decode JSON string to array
                $memberIds = json_decode($project->member_id, true);
                if (is_array($memberIds)) {
                    // Fetch user names for member_ids
                    $members = User::whereIn('user_id', $memberIds)
                        ->pluck('name')
                        ->toArray();
                    $project->members = $members;
                } else {
                    $project->members = [];
                }
            } else {
                $project->members = [];
            }
            return $project;
        });

        return response()->json([
            'status' => true,
            'message' => 'Projects fetched successfully.',
            'data' => $projects
        ], 200);
    } catch (\Exception $e) {
        Log::error('Error fetching projects: ' . $e->getMessage());
        return response()->json([
            'status' => false,
            'message' => 'Failed to fetch projects.',
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function yourProjects(Request $request)
{
    try {
        $userId = Auth::id();
        $projects = Project::with([
            'user:user_id,name',
            'contract:contract_id,title,time_line_category,start_date,end_date,pdf_file,status,performance_guarantee',
            'tender:tender_id,tender_type,attachment'
        ])
        ->where('user_id', $userId)
        ->get([
            'project_id',
            'project_name',
            'tender_id',
            'user_id',
            'contract_id',
            'member_id',
            'created_by',
            'project_status',
            'start_date',
            'end_date',
            'extended_date',
            'follow_up',
            'created_at',
            'updated_at'
        ]);

        // Process member_id to fetch user names
        $projects = $projects->map(function ($project) {
            if ($project->member_id) {
                // Decode JSON string to array
                $memberIds = json_decode($project->member_id, true);
                if (is_array($memberIds)) {
                    // Fetch user names for member_ids
                    $members = User::whereIn('user_id', $memberIds)
                        ->pluck('name')
                        ->toArray();
                    $project->members = $members;
                } else {
                    $project->members = [];
                }
            } else {
                $project->members = [];
            }
            return $project;
        });

        return response()->json([
            'status' => true,
            'message' => 'Your projects fetched successfully.',
            'data' => $projects
        ], 200);
    } catch (\Exception $e) {
        Log::error('Error fetching user projects: ' . $e->getMessage());
        return response()->json([
            'status' => false,
            'message' => 'Failed to fetch your projects.',
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function show($project_id)
    {
        try {
            $project = Project::with(['user:user_id,name', 'contract:contract_id,title'])
                ->findOrFail($project_id);

            return response()->json([
                'status' => true,
                'message' => 'Project fetched successfully.',
                'data' => $project
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching project: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Project not found.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_name' => 'required|string|max:255',
            'tender_id' => 'nullable|exists:tenders,tender_id',
            'user_id' => 'required|exists:users,user_id',
            'contract_id' => 'required|exists:contracts,contract_id',
            'member_id' => 'nullable|array',
            'member_id.*' => 'exists:users,user_id',
            'created_by' => 'required|string|max:255',
            'project_status' => 'nullable|string|in:on-progress,completed,failed',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $project = Project::create([
                'project_name' => $request->project_name,
                'tender_id' => $request->tender_id,
                'user_id' => $request->user_id,
                'contract_id' => $request->contract_id,
                'member_id' => $request->member_id ? json_encode($request->member_id) : null,
                'created_by' => $request->created_by,
                'project_status' => $request->input('project_status', 'on-progress'),
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ]);

            $user = User::findOrFail($request->user_id);
            $this->sendProjectAssignedEmailToUser($user, $project);

            return response()->json([
                'status' => true,
                'message' => 'Project created and assigned successfully.',
                'data' => $project
            ], 201);
        } catch (\Exception $e) {
            Log::error('Project creation error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to create project.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $project_id)
    {
        $validator = Validator::make($request->all(), [
            'project_name' => 'sometimes|string|max:255',
            'tender_id' => 'sometimes|exists:tenders,tender_id',
            'user_id' => 'sometimes|exists:users,user_id',
            'contract_id' => 'sometimes|exists:contracts,contract_id',
            'member_id' => 'sometimes|array',
            'member_id.*' => 'exists:users,user_id',
            'created_by' => 'sometimes|string|max:255',
            'project_status' => 'sometimes|string|in:on-progress,completed,failed',
            'follow_up' => 'sometimes|string|in:on-progress,completed',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'extended_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $project = Project::findOrFail($project_id);

            $updatedData = $request->only([
                'project_name',
                'tender_id',
                'user_id',
                'contract_id',
                'member_id',
                'created_by',
                'project_status',
                'follow_up',
                'start_date',
                'end_date',
                'extended_date'
            ]);

            if (isset($updatedData['member_id'])) {
                $updatedData['member_id'] = $updatedData['member_id'] ? json_encode($updatedData['member_id']) : null;
            }

            $project->update($updatedData);

            return response()->json([
                'status' => true,
                'message' => 'Project updated successfully.',
                'data' => $project
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error during project update: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to update project.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function countOnProgressUserProjects()
{
    try {
        $userId = Auth::id();
        $count = Project::where('user_id', $userId)
            ->where('project_status', 'on-progress')
            ->count();

        return response()->json([
            'status' => true,
            'message' => 'Total on-progress projects for user fetched successfully.',
            'total_on_progress_projects' => $count
        ], 200);
    } catch (\Exception $e) {
        Log::error('Error counting on-progress user projects: ' . $e->getMessage());
        return response()->json([
            'status' => false,
            'message' => 'Failed to count on-progress projects.',
            'error' => $e->getMessage()
        ], 500);
    }
}


public function usersWithProjectSummary()
{
    try {
        $users = User::select('users.*')
            ->where('role_id', 3) // Filter users with role_id = 3
            ->addSelect([
                'total_projects' => Project::selectRaw('count(*)')
                    ->whereColumn('projects.user_id', 'users.user_id'),
                'on_progress_projects' => Project::selectRaw('count(*)')
                    ->whereColumn('projects.user_id', 'users.user_id')
                    ->where('projects.project_status', 'on-progress'),
                'completed_projects' => Project::selectRaw('count(*)')
                    ->whereColumn('projects.user_id', 'users.user_id')
                    ->where('projects.project_status', 'completed'),
                'failed_projects' => Project::selectRaw('count(*)')
                    ->whereColumn('projects.user_id', 'users.user_id')
                    ->where('projects.project_status', 'failed'),
            ])
            ->with([
                'role' => fn($query) => $query->select('role_id', 'category'),
                'department' => fn($query) => $query->select('department_id', 'name'),
                'projects' => fn($query) => $query->select([
                    'project_id',
                    'project_name',
                    'tender_id',
                    'user_id',
                    'contract_id',
                    'created_by',
                    'project_status',
                    'start_date',
                    'end_date',
                    'extended_date',
                    'follow_up',
                    'created_at',
                    'updated_at'
                ])->with([
                    'user' => fn($query) => $query->select('user_id', 'name'),
                    'contract' => fn($query) => $query->select('contract_id', 'title', 'time_line_category', 'start_date', 'end_date', 'pdf_file', 'status', 'performance_guarantee'),
                    'tender' => fn($query) => $query->select('tender_id', 'tender_type', 'attachment')
                ])
            ])
            ->orderBy('users.user_id', 'desc')
            ->get()
            ->map(function ($user) {
                return [
                    'user_id' => $user->user_id,
                    'name' => $user->name ?? 'NA',
                    'email' => $user->email ?? 'NA',
                    'status' => $user->status ?? 'NA',
                    'role' => optional($user->role)->category ?? 'NA',
                    'department' => optional($user->department)->name ?? 'NA',
                    'total_projects' => $user->total_projects ?? 0,
                    'on_progress_projects' => $user->on_progress_projects ?? 0,
                    'completed_projects' => $user->completed_projects ?? 0,
                    'failed_projects' => $user->failed_projects ?? 0,
                    'projects' => $user->projects->map(function ($project) {
                        return [
                            'project_id' => $project->project_id,
                            'project_name' => $project->project_name ?? 'NA',
                            'project_status' => $project->project_status ?? 'NA',
                            'start_date' => $project->start_date ?? 'NA',
                            'end_date' => $project->end_date ?? 'NA',
                            'extended_date' => $project->extended_date ?? 'NA',
                            'follow_up' => $project->follow_up ?? 'NA',
                            'created_by' => $project->created_by ?? 'NA',
                            'user' => optional($project->user)->name ?? 'NA',
                            'contract' => $project->contract ? [
                                'title' => $project->contract->title ?? 'NA',
                                'status' => $project->contract->status ?? 'NA',
                                'time_line_category' => $project->contract->time_line_category ?? 'NA',
                                'performance_guarantee' => $project->contract->performance_guarantee ?? 'NA',
                            ] : null,
                            'tender' => $project->tender ? [
                                'tender_type' => $project->tender->tender_type ?? 'NA',
                                'attachment' => $project->tender->attachment ?? 'NA',
                            ] : null,
                        ];
                    })->values()
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'Users with role_id 3 and project summary fetched successfully.',
            'data' => $users
        ], 200);
    } catch (\Exception $e) {
        \Log::error('Error fetching users with role_id 3 and project summary: ' . $e->getMessage(), ['exception' => $e]);
        return response()->json([
            'status' => false,
            'message' => 'Failed to fetch users with project summary.',
            'error' => $e->getMessage()
        ], 500);
    }
}
 
    
    public function destroy($project_id)
    {
        try {
            $project = Project::findOrFail($project_id);
            $project->delete();

            return response()->json([
                'status' => true,
                'message' => 'Project deleted successfully.'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting project: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete project.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function allProjectsDropDown(Request $request)
    {
        try {
            $projects = Project::select('project_id', 'project_name')->get();

            return response()->json([
                'status' => true,
                'message' => 'Projects fetched successfully for dropdown.',
                'data' => $projects
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching projects for dropdown: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch projects for dropdown.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getContractTitles()
    {
        try {
            $contracts = Contract::select('contract_id', 'title')
                ->orderBy('contract_id', 'desc')
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Contract titles fetched successfully for dropdown.',
                'data' => $contracts
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching contract titles: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch contract titles.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function checkProjectDueDates()
    {
        try {
            $currentDate = Carbon::now();
            $projects = Project::where('project_status', 'on-progress')
                ->whereDate('start_date', '<=', $currentDate)
                ->whereDate('end_date', '>=', $currentDate)
                ->get();

            foreach ($projects as $project) {
                $endDate = Carbon::parse($project->end_date);
                $remainingDays = $currentDate->diffInDays($endDate, false);

                if ($remainingDays <= 7 && $remainingDays >= 0) {
                    $user = $project->user;
                    if ($user) {
                        $this->sendDeadlineReminderEmail($user, $project);
                    }
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Deadline reminder emails sent successfully.'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error while checking project due dates: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to send reminder emails.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function countAllUserProjects()
    {
        try {
            $userId = Auth::id();
            $count = Project::where('user_id', $userId)->count();

            return response()->json([
                'status' => true,
                'message' => 'Total projects fetched successfully.',
                'total_projects' => $count
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error counting user projects: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to count projects.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function countFailedUserProjects()
    {
        try {
            $userId = Auth::id();
            $count = Project::where('user_id', $userId)
                ->where('project_status', 'failed')
                ->count();

            return response()->json([
                'status' => true,
                'message' => 'Total failed projects fetched successfully.',
                'total_failed_projects' => $count
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error counting failed user projects: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to count failed projects.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function countCompletedUserProjects()
    {
        try {
            $userId = Auth::id();
            $count = Project::where('user_id', $userId)
                ->where('project_status', 'completed')
                ->count();

            return response()->json([
                'status' => true,
                'message' => 'Total completed projects fetched successfully.',
                'total_completed_projects' => $count
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error counting completed user projects: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to count completed projects.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function countUserTotalBudget()
    {
        try {
            $userId = Auth::id();
            $totalBudget = Project::where('user_id', $userId)->sum('budget');

            return response()->json([
                'status' => true,
                'message' => 'Total budget for user projects calculated successfully.',
                'data' => [
                    'total_user_budget' => $totalBudget
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error calculating total budget for user: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error calculating total budget for user.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function countProjects()
    {
        try {
            $count = Project::count();

            return response()->json([
                'status' => true,
                'message' => 'Total projects count fetched successfully.',
                'count_total_projects' => $count
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error counting projects: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to count projects.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function countFailedProjects()
    {
        try {
            $count = Project::where('project_status', 'failed')->count();

            return response()->json([
                'status' => true,
                'message' => 'Total failed projects fetched successfully.',
                'total_failed_projects' => $count
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error counting failed projects: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to count failed projects.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function countCompletedProjects()
    {
        try {
            $count = Project::where('project_status', 'completed')->count();

            return response()->json([
                'status' => true,
                'message' => 'Total completed projects fetched successfully.',
                'total_completed_projects' => $count
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error counting completed projects: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to count completed projects.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function countAllOnProgressProjects()
    {
        try {
            $count = Project::where('project_status', 'on-progress')->count();

            return response()->json([
                'status' => true,
                'message' => 'Total on-progress projects fetched successfully.',
                'total_on_progress_projects' => $count
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error counting on-progress projects: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to count on-progress projects.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function countTotalBudget()
    {
        try {
            $totalBudget = Project::sum('budget');

            return response()->json([
                'status' => true,
                'message' => 'Total budget calculated successfully.',
                'data' => [
                    'total_budget' => $totalBudget
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error calculating total budget: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error calculating total budget.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getProjectsReports(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'from' => 'required|date',
                'to' => 'required|date|after_or_equal:from',
            ]);

            $projects = Project::with(['user:user_id,name', 'contract:contract_id,title'])
                ->whereBetween('created_at', [$validatedData['from'], $validatedData['to']])
                ->orderBy('created_at', 'desc')
                ->get(['project_id', 'project_name', 'tender_id', 'user_id', 'contract_id', 'member_id', 'created_by', 'project_status', 'follow_up', 'start_date', 'end_date', 'extended_date', 'created_at']);

            if ($projects->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No projects found.',
                    'error' => 'No query results for model [App\\Models\\Project].'
                ], 404);
            }

            $formattedProjects = $projects->map(function ($project) {
                return [
                    'project_id' => $project->project_id,
                    'project_name' => $project->project_name,
                    'contract_id' => $project->contract_id,
                    'contract_title' => $project->contract ? $project->contract->title : null,
                    'member_id' => $project->member_id,
                    'project_status' => $project->project_status,
                    'follow_up' => $project->follow_up,
                    'start_date' => Carbon::parse($project->start_date)->toIso8601String(),
                    'end_date' => Carbon::parse($project->end_date)->toIso8601String(),
                    'extended_date' => $project->extended_date ? Carbon::parse($project->extended_date)->toIso8601String() : null,
                    'created_at' => Carbon::parse($project->created_at)->toIso8601String(),
                    'user' => [
                        'user_id' => $project->user->user_id,
                        'name' => $project->user->name,
                    ]
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Projects fetched successfully.',
                'data' => $formattedProjects
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error fetching projects report: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching the report.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function sendProjectAssignedEmailToUser(User $user, Project $project)
    {
        $subject = 'New Project Assigned: ' . $project->project_name;
        $emailBody = "Dear {$user->name},\n\n"
            . "You have been assigned a new project with the following details:\n\n"
            . "Project Name: {$project->project_name}\n"
            . "Start Date: {$project->start_date}\n"
            . "End Date: {$project->end_date}\n"
            . "Contract ID: {$project->contract_id}\n"
            . "Team Members: " . ($project->member_id ? implode(', ', json_decode($project->member_id, true)) : 'None') . "\n\n"
            . "Please log in to the portal for further details.\n\n"
            . "Thank you.";

        Mail::raw($emailBody, function ($message) use ($user, $subject) {
            $message->to($user->email)->subject($subject);
        });
    }

    private function sendDeadlineReminderEmail(User $user, Project $project)
    {
        $subject = 'Project Deadline Reminder: ' . $project->project_name;
        $emailBody = "Dear {$user->name},\n\n"
            . "This is a reminder that the project '{$project->project_name}' is approaching its deadline.\n\n"
            . "Project Name: {$project->project_name}\n"
            . "Deadline: {$project->end_date}\n"
            . "Follow-up Status: " . ($project->follow_up ?? 'Not set') . "\n\n"
            . "Please make sure to complete any pending tasks before the deadline.\n\n"
            . "Thank you.";

        Mail::raw($emailBody, function ($message) use ($user, $subject) {
            $message->to($user->email)->subject($subject);
        });
    }
}
?>