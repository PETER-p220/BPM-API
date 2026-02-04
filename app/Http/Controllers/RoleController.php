<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RoleController extends Controller
{

      public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['register', 'login']);
    }


    // Get all roles
    public function index()
    {
        try {
            $roles = Role::all();
            return response()->json($roles, Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to retrieve roles'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

      public function store(Request $request)
    {
        $validatedData = $request->validate([
            'category' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        try {
            $role = Role::create($validatedData);
            return response()->json($role, Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create role'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    // Get a single role by role_id
    public function show($role_id)
    {
        try {
            $role = Role::findOrFail($role_id);
            return response()->json($role, Response::HTTP_OK);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Role not found'], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to retrieve role'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }



    // Update an existing role
    public function update(Request $request, $role_id)
    {
        $validatedData = $request->validate([
            'category' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        try {
            $role = Role::findOrFail($role_id);
            $role->update($validatedData);
            return response()->json($role, Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update role'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Delete a role
    public function destroy($role_id)
    {
        try {
            $role = Role::findOrFail($role_id);
            $role->delete();
            return response()->json(['message' => 'Role deleted successfully'], Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete role'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    public function countRoles()
{
    try {
        $count = Role::count();
        return response()->json(['user_roles' => $count], Response::HTTP_OK);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Failed to count roles'], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

}
