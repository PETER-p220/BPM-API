<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = User::with(['role', 'department'])
                ->whereNotIn('users.role_id', [1, 7]); // Exclude Admin (1) and CEO (7)

            // Apply filters
            if ($request->has('status') && $request->status) {
                $query->where('users.status', $request->status);
            }

            if ($request->has('department') && $request->department) {
                if (is_numeric($request->department)) {
                    $query->where('users.department_id', $request->department);
                } else {
                    $query->whereHas('department', function ($q) use ($request) {
                        $q->where('name', 'like', "%{$request->department}%");
                    });
                }
            }

            if ($request->has('role') && $request->role) {
                $query->where('users.role_id', $request->role);
            }

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('users.name', 'like', "%{$search}%")
                      ->orWhere('users.email', 'like', "%{$search}%");
                });
            }

            $employees = $query->orderBy('users.created_at', 'desc')->get();

            // Transform the data to match expected format
            $transformedEmployees = $employees->map(function ($user) {
                return [
                    'id' => $user->user_id,
                    'first_name' => explode(' ', $user->name)[0] ?? '',
                    'last_name' => explode(' ', $user->name)[1] ?? '',
                    'email' => $user->email,
                    'phone' => $user->phone ?? 'N/A',
                    'department' => $user->department ? $user->department->name : 'N/A',
                    'position' => $user->role ? $user->role->description : 'N/A',
                    'salary' => $user->salary,
                    'hire_date' => $user->hire_date ? $user->hire_date->format('Y-m-d') : ($user->created_at ? $user->created_at->format('Y-m-d') : null),
                    'status' => $user->status ?? 'active',
                    'address' => $user->address ?? null,
                    'emergency_contact' => $user->emergency_contact ?? null,
                    'emergency_phone' => $user->emergency_phone ?? null,
                    'birth_date' => $user->birth_date ?? null,
                    'gender' => $user->gender ?? null,
                    'national_id' => $user->national_id ?? null,
                    'bank_account' => $user->bank_account ?? null,
                    'bank_name' => $user->bank_name ?? null,
                    'notes' => $user->notes ?? null,
                    'role_id' => $user->role_id,
                    'department_id' => $user->department_id,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $transformedEmployees,
                'message' => 'Employees retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve employees: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'phone' => 'nullable|string|max:20',
                'department_id' => 'required|exists:departments,department_id',
                'role_id' => 'required|exists:roles,role_id|in:2,3,4,5,6,8,9',
                'salary' => 'nullable|numeric|min:0',
                'hire_date' => 'nullable|date',
                'address' => 'nullable|string',
                'emergency_contact' => 'nullable|string|max:255',
                'emergency_phone' => 'nullable|string|max:20',
                'birth_date' => 'nullable|date',
                'gender' => 'nullable|string|in:male,female,other',
                'national_id' => 'nullable|string|max:50',
                'bank_account' => 'nullable|string|max:50',
                'bank_name' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
                'password' => 'required|string|min:6',
                'status' => 'nullable|in:active,inactive,on_leave'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userData = $request->all();
            $userData['password'] = bcrypt($request->password);
            
            $user = User::create($userData);

            return response()->json([
                'status' => 'success',
                'data' => $user,
                'message' => 'Employee created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create employee: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $user = User::with(['role', 'department'])->findOrFail($id);

            // Transform the data to match expected format
            $transformedUser = [
                'id' => $user->user_id,
                'first_name' => explode(' ', $user->name)[0] ?? '',
                'last_name' => explode(' ', $user->name)[1] ?? '',
                'email' => $user->email,
                'phone' => $user->phone ?? 'N/A',
                'department' => $user->department ? $user->department->name : 'N/A',
                'position' => $user->role ? $user->role->description : 'N/A',
                'salary' => $user->salary,
                'hire_date' => $user->hire_date ? $user->hire_date->format('Y-m-d') : ($user->created_at ? $user->created_at->format('Y-m-d') : null),
                'status' => $user->status ?? 'active',
                'address' => $user->address ?? null,
                'emergency_contact' => $user->emergency_contact ?? null,
                'emergency_phone' => $user->emergency_phone ?? null,
                'birth_date' => $user->birth_date ?? null,
                'gender' => $user->gender ?? null,
                'national_id' => $user->national_id ?? null,
                'bank_account' => $user->bank_account ?? null,
                'bank_name' => $user->bank_name ?? null,
                'notes' => $user->notes ?? null,
                'role_id' => $user->role_id,
                'department_id' => $user->department_id,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at
            ];

            return response()->json([
                'status' => 'success',
                'data' => $transformedUser,
                'message' => 'Employee retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Employee not found'
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $user = User::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|unique:users,email,' . $id,
                'phone' => 'nullable|string|max:20',
                'department_id' => 'sometimes|required|exists:departments,department_id',
                'role_id' => 'sometimes|required|exists:roles,role_id|in:2,3,4,5,6,8,9',
                'salary' => 'sometimes|nullable|numeric|min:0',
                'hire_date' => 'sometimes|nullable|date',
                'address' => 'nullable|string',
                'emergency_contact' => 'nullable|string|max:255',
                'emergency_phone' => 'nullable|string|max:20',
                'birth_date' => 'nullable|date',
                'gender' => 'nullable|string|in:male,female,other',
                'national_id' => 'nullable|string|max:50',
                'bank_account' => 'nullable|string|max:50',
                'bank_name' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
                'status' => 'sometimes|in:active,inactive,on_leave'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user->update($request->all());

            return response()->json([
                'status' => 'success',
                'data' => $user,
                'message' => 'Employee updated successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update employee: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $user = User::findOrFail($id);
            $user->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Employee deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete employee: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employee statistics
     */
    public function statistics()
    {
        try {
            $stats = [
                'total' => User::whereNotIn('role_id', [1, 7])->count(),
                'active' => User::where('status', 'active')->whereNotIn('role_id', [1, 7])->count(),
                'inactive' => User::where('status', 'inactive')->whereNotIn('role_id', [1, 7])->count(),
                'on_leave' => User::where('status', 'on_leave')->whereNotIn('role_id', [1, 7])->count(),
                'by_department' => User::join('departments', 'users.department_id', '=', 'departments.department_id')
                    ->whereNotIn('users.role_id', [1, 7])
                    ->select('departments.name as department', DB::raw('count(*) as count'))
                    ->groupBy('departments.name')
                    ->get(),
                'by_role' => User::join('roles', 'users.role_id', '=', 'roles.role_id')
                    ->whereNotIn('users.role_id', [1, 7])
                    ->select('roles.category as role', DB::raw('count(*) as count'))
                    ->groupBy('roles.category')
                    ->get()
            ];

            return response()->json([
                'status' => 'success',
                'data' => $stats,
                'message' => 'Statistics retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export employees to Excel
     */
    public function export(Request $request)
    {
        try {
            $query = User::with(['role', 'department'])
                ->whereNotIn('users.role_id', [1, 7]); // Exclude Admin (1) and CEO (7)

            // Apply same filters as index method
            if ($request->has('status') && $request->status) {
                $query->where('users.status', $request->status);
            }

            if ($request->has('department') && $request->department) {
                if (is_numeric($request->department)) {
                    $query->where('users.department_id', $request->department);
                } else {
                    $query->whereHas('department', function ($q) use ($request) {
                        $q->where('name', 'like', "%{$request->department}%");
                    });
                }
            }

            if ($request->has('role') && $request->role) {
                $query->where('users.role_id', $request->role);
            }

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('users.name', 'like', "%{$search}%")
                      ->orWhere('users.email', 'like', "%{$search}%");
                });
            }

            $employees = $query->orderBy('users.name', 'asc')->get();

            // Format data for export
            $exportData = $employees->map(function ($user, $index) {
                return [
                    '#' => $index + 1,
                    'Name' => $user->name,
                    'Email' => $user->email,
                    'Phone' => $user->phone ?? 'N/A',
                    'Department' => $user->department ? $user->department->name : 'N/A',
                    'Position' => $user->role ? $user->role->description : 'N/A',
                    'Role' => $user->role ? $user->role->category : 'N/A',
                    'Status' => ucfirst(str_replace('_', ' ', $user->status ?? 'active')),
                    'Hire Date' => $user->created_at ? $user->created_at->format('d M Y') : 'N/A',
                    'Emergency Contact' => $user->emergency_contact ?? 'N/A',
                    'Emergency Phone' => $user->emergency_phone ?? 'N/A'
                ];
            })->toArray();

            return response()->json([
                'status' => 'success',
                'data' => $exportData,
                'message' => 'Employees exported successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to export employees: ' . $e->getMessage()
            ], 500);
        }
    }
}
