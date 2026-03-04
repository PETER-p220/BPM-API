<?php

namespace App\Http\Controllers;

use App\Models\Leave;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $query = Leave::with(['employee:user_id,name,email,department_id', 
                            'employee.department:department_id,name',
                            'approver:user_id,name,email']);

        // Check if user has permission
        // HR/Admin/CEO can view all leaves, employees can only view their own
        if (!in_array($user->role_id, [1, 2, 6, 7])) {
            // Employee can only see their own leaves
            $query->where('employee_id', $user->user_id);
        }

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }
        
        if ($request->filled('department_id')) {
            $query->whereHas('employee', function($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }
        
        if ($request->filled('start_date')) {
            $query->whereDate('start_date', '>=', $request->start_date);
        }
        
        if ($request->filled('end_date')) {
            $query->whereDate('end_date', '<=', $request->end_date);
        }

        $leaves = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => true,
            'data' => $leaves
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        
        // Check if user has permission
        // Employees can create leave for themselves, HR/Admin/CEO can create for anyone
        if (!in_array($user->role_id, [1, 2, 6, 7])) {
            // Employee can only create leave for themselves
            if ($request->employee_id != $user->user_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized. Employees can only create leave requests for themselves.'
                ], 403);
            }
        }

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:users,user_id',
            'leave_type' => 'required|in:sick,vacation,maternity,paternity,emergency,unpaid',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|min:2',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors.',
                'errors' => $validator->errors()
            ], 400);
        }

        // Check for overlapping leaves
        $overlapping = Leave::where('employee_id', $request->employee_id)
            ->where(function($query) use ($request) {
                $query->where(function($q) use ($request) {
                    $q->whereBetween('start_date', [$request->start_date, $request->end_date])
                      ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
                      ->orWhere(function($q) use ($request) {
                          $q->where('start_date', '<=', $request->start_date)
                            ->where('end_date', '>=', $request->end_date);
                      });
                });
            })
            ->where('status', '!=', 'rejected')
            ->exists();

        if ($overlapping) {
            return response()->json([
                'status' => false,
                'message' => 'Employee already has leave requested for this period.'
            ], 400);
        }

        // Calculate leave days
        $start = new \DateTime($request->start_date);
        $end = new \DateTime($request->end_date);
        $days = $start->diff($end)->days + 1;

        $leave = Leave::create([
            'employee_id' => $request->employee_id,
            'leave_type' => $request->leave_type,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'days' => $days,
            'reason' => $request->reason,
            'status' => 'pending',
            'requested_by' => $user->user_id,
            'attachments' => $request->attachments ? json_encode($request->attachments) : null
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Leave request created successfully.',
            'data' => $leave->load(['employee:user_id,name,email,department_id', 'employee.department:department_id,name'])
        ], 201);
    }

    public function show($id)
    {
        $user = Auth::user();
        
        // Check if user has permission
        // HR/Admin/CEO can view any leave, employees can only view their own
        if (!in_array($user->role_id, [1, 2, 6, 7])) {
            // Employee can only see their own leave details
            $leave = Leave::with(['employee:user_id,name,email,department_id', 
                               'employee.department:department_id,name',
                               'approver:user_id,name,email',
                               'requester:user_id,name,email'])
                        ->where('employee_id', $user->user_id)
                        ->find($id);
            
            if (!$leave) {
                return response()->json([
                    'status' => false,
                    'message' => 'Leave not found or unauthorized.'
                ], 404);
            }
        } else {
            // HR/Admin/CEO can view any leave
            $leave = Leave::with(['employee:user_id,name,email,department_id', 
                               'employee.department:department_id,name',
                               'approver:user_id,name,email',
                               'requester:user_id,name,email'])
                        ->find($id);
            
            if (!$leave) {
                return response()->json([
                    'status' => false,
                    'message' => 'Leave not found.'
                ], 404);
            }
        }

        return response()->json([
            'status' => true,
            'data' => $leave
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        
        // Check if user has permission
        // HR/Admin/CEO can update any leave, employees can only update their own pending leaves
        if (!in_array($user->role_id, [1, 2, 6, 7])) {
            // Employee can only update their own pending leaves
            $leave = Leave::where('employee_id', $user->user_id)
                          ->where('status', 'pending')
                          ->find($id);
            
            if (!$leave) {
                return response()->json([
                    'status' => false,
                    'message' => 'Leave not found or cannot be updated. Only pending leaves can be updated.'
                ], 404);
            }
        } else {
            // HR/Admin/CEO can update any leave
            $leave = Leave::find($id);
            if (!$leave) {
                return response()->json([
                    'status' => false,
                    'message' => 'Leave not found.'
                ], 404);
            }
        }

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:users,user_id',
            'leave_type' => 'required|in:sick,vacation,maternity,paternity,emergency,unpaid',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|min:2',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:2048',
            'status' => 'sometimes|in:pending,approved,rejected,cancelled'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors.',
                'errors' => $validator->errors()
            ], 400);
        }
        // If status is being changed to approved/rejected, set approver
        if ($request->filled('status') && in_array($request->status, ['approved', 'rejected'])) {
            $leave->approver_id = $user->user_id;
            $leave->approved_at = now();
        }
        $leave->update($request->all());
        return response()->json([
            'status' => true,
            'message' => 'Leave updated successfully.',
            'data' => $leave->load(['employee:user_id,name,email,department_id', 'employee.department:department_id,name'])
        ]);
    }
    public function approve(Request $request, $id)
    {
        $user = Auth::user();
        // Check if user has permission (HR, Admin, CEO)
        if (!in_array($user->role_id, [1, 2, 6, 7])) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized. Only HR, Admin, and CEO can approve leaves.'
            ], 403);
        }

        $leave = Leave::find($id);
        if (!$leave) {
            return response()->json([
                'status' => false,
                'message' => 'Leave not found.'
            ], 404);
        }

        if (strtolower($leave->status) !== 'pending') {
            // Debug: Log current status
            \Log::info('Leave approval attempt', [
                'leave_id' => $leave->id,
                'current_status' => $leave->status,
                'status_type' => gettype($leave->status),
                'status_trimmed' => trim($leave->status),
                'status_lowercase' => strtolower($leave->status)
            ]);
            
            return response()->json([
                'status' => false,
                'message' => 'Leave can only be approved if status is pending. Current status: ' . $leave->status
            ], 400);
        }

        $leave->update([
            'status' => 'approved',
            'approver_id' => $user->user_id,
            'approved_at' => now()
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Leave approved successfully.',
            'data' => $leave->load(['employee:user_id,name,email,department_id', 'employee.department:department_id,name'])
        ]);
    }

    public function reject(Request $request, $id)
    {
        $user = Auth::user();
        
        // Check if user has permission (HR, Admin, CEO)
        if (!in_array($user->role_id, [1, 2, 6, 7])) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized. Only HR, Admin, and CEO can reject leaves.'
            ], 403);
        }

        $leave = Leave::find($id);
        if (!$leave) {
            return response()->json([
                'status' => false,
                'message' => 'Leave not found.'
            ], 404);
        }

        if (strtolower($leave->status) !== 'pending') {
            return response()->json([
                'status' => false,
                'message' => 'Leave can only be rejected if status is pending.'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|min:5'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors.',
                'errors' => $validator->errors()
            ], 400);
        }

        $leave->update([
            'status' => 'rejected',
            'approver_id' => $user->user_id,
            'approved_at' => now(),
            'rejection_reason' => $request->rejection_reason
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Leave rejected successfully.',
            'data' => $leave->load(['employee:user_id,name,email,department_id', 'employee.department:department_id,name'])
        ]);
    }

    public function destroy($id)
    {
        $user = Auth::user();
        
        // Check if user has permission
        // HR/Admin/CEO can delete any leave, employees can only delete their own pending leaves
        if (!in_array($user->role_id, [1, 2, 6, 7])) {
            // Employee can only delete their own pending leaves
            $leave = Leave::where('employee_id', $user->user_id)
                          ->where('status', 'pending')
                          ->find($id);
            
            if (!$leave) {
                return response()->json([
                    'status' => false,
                    'message' => 'Leave not found or cannot be deleted. Only pending leaves can be deleted.'
                ], 404);
            }
        } else {
            // HR/Admin/CEO can delete any leave
            $leave = Leave::find($id);
            if (!$leave) {
                return response()->json([
                    'status' => false,
                    'message' => 'Leave not found.'
                ], 404);
            }
        }

        $leave->delete();

        return response()->json([
            'status' => true,
            'message' => 'Leave deleted successfully.'
        ]);
    }

    public function statistics()
    {
        $user = Auth::user();
        
        // For regular users (3,4,5), show only their own statistics
        if (in_array($user->role_id, [3, 4, 5])) {
            $stats = [
                'total' => Leave::where('employee_id', $user->user_id)->count(),
                'pending' => Leave::where('employee_id', $user->user_id)
                              ->where(function($query) {
                                  $query->where('status', 'pending')
                                        ->orWhere('status', 'Pending');
                              })->count(),
                'approved' => Leave::where('employee_id', $user->user_id)
                               ->where(function($query) {
                                   $query->where('status', 'approved')
                                         ->orWhere('status', 'Approved');
                               })->count(),
                'rejected' => Leave::where('employee_id', $user->user_id)
                               ->where(function($query) {
                                   $query->where('status', 'rejected')
                                         ->orWhere('status', 'Rejected');
                               })->count(),
                'cancelled' => Leave::where('employee_id', $user->user_id)
                                ->where(function($query) {
                                    $query->where('status', 'cancelled')
                                          ->orWhere('status', 'Cancelled');
                                })->count(),
                'this_month' => Leave::where('employee_id', $user->user_id)
                                  ->whereMonth('created_at', now()->month)
                                  ->whereYear('created_at', now()->year)
                                  ->count(),
                'by_type' => Leave::where('employee_id', $user->user_id)
                             ->selectRaw('leave_type, COUNT(*) as count')
                             ->groupBy('leave_type')
                             ->get(),
                'by_department' => collect([]) // Empty for regular users
            ];
        } else {
            // For HR/Admin/CEO, show all statistics
            $stats = [
                'total' => Leave::count(),
                'pending' => Leave::where(function($query) {
                    $query->where('status', 'pending')
                          ->orWhere('status', 'Pending');
                })->count(),
                'approved' => Leave::where(function($query) {
                    $query->where('status', 'approved')
                          ->orWhere('status', 'Approved');
                })->count(),
                'rejected' => Leave::where(function($query) {
                    $query->where('status', 'rejected')
                          ->orWhere('status', 'Rejected');
                })->count(),
                'cancelled' => Leave::where(function($query) {
                    $query->where('status', 'cancelled')
                          ->orWhere('status', 'Cancelled');
                })->count(),
                'this_month' => Leave::whereMonth('created_at', now()->month)
                                    ->whereYear('created_at', now()->year)
                                    ->count(),
                'by_type' => Leave::selectRaw('leave_type, COUNT(*) as count')
                            ->groupBy('leave_type')
                            ->get(),
                'by_department' => DB::table('leaves')
                                    ->join('users', 'leaves.employee_id', '=', 'users.user_id')
                                    ->join('departments', 'users.department_id', '=', 'departments.department_id')
                                    ->selectRaw('departments.name, COUNT(*) as count')
                                    ->groupBy('departments.name')
                                    ->get()
            ];
        }

        return response()->json([
            'status' => true,
            'data' => $stats
        ]);
    }
}
