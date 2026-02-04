<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DepartmentController extends Controller
{
    // Create a new department
    public function store(Request $request)
    {
        try {
            // Validate the incoming request data
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'location' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Create department
            $department = Department::create($request->all());

            return response()->json(['message' => 'Department created successfully', 'data' => $department], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error creating department', 'error' => $e->getMessage()], 500);
        }
    }
// Read all departments
public function index()
{
    try {
        $departments = Department::all();
        return response()->json(['data' => $departments], 200);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Error fetching departments', 'error' => $e->getMessage()], 500);
    }
}



    public function departmentByDropDown(Request $request)
{
    try {
        // Fetch departments and select only 'department_id' and 'name'
        $departments = Department::orderBy('department_id', 'asc') // Optionally order by department_id
                                 ->get()
                                 ->map(function ($department) {
                                     return [
                                         'department_id' => $department->department_id, // department_id to use in the dropdown value
                                         'name' => $department->name,                    // name to display in the dropdown option
                                     ];
                                 });

        // Return the response with the list of departments for the dropdown
        return response()->json(['departments' => $departments], 200);
    } catch (\Exception $e) {
        // Log the error for debugging
        \Log::error('Error fetching departments for dropdown: ' . $e->getMessage());

        // Return a JSON error response with HTTP status 500
        return response()->json(['error' => 'Failed to fetch departments for dropdown.'], 500);
    }
}


    // Show a single department by ID
    public function show($department_id)
{
    try {
        $department = Department::findOrFail($department_id); // Change 'id' to 'department_id'
        return response()->json(['data' => $department], 200);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Department not found', 'error' => $e->getMessage()], 404);
    }
}


    // Update an existing department
    public function update(Request $request, $department_id)
{
    try {
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $department = Department::findOrFail($department_id); // Change 'id' to 'department_id'
        $department->update($request->all());

        return response()->json(['message' => 'Department updated successfully', 'data' => $department], 200);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Error updating department', 'error' => $e->getMessage()], 500);
    }
}


    // Delete a department
    public function destroy($id)
    {
        try {
            $department = Department::findOrFail($id);
            $department->delete();

            return response()->json(['message' => 'Department deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error deleting department', 'error' => $e->getMessage()], 500);
        }
    }

    public function countDepartments()
{
    try {
        $count = Department::count();
        return response()->json(['total_departments' => $count], 200);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Error counting departments', 'error' => $e->getMessage()], 500);
    }
}

}
