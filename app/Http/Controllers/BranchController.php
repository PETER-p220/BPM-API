<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BranchController extends Controller
{
    public function index()
    {
        return response()->json([
            'status' => true,
            'data'   => Branch::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'    => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
        }

        $branch = Branch::create($request->only('name', 'address'));

        return response()->json(['status' => true, 'message' => 'Branch created.', 'data' => $branch], 201);
    }

    public function update(Request $request, $id)
    {
        $branch = Branch::find($id);
        if (!$branch) {
            return response()->json(['status' => false, 'message' => 'Branch not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'    => 'sometimes|required|string|max:255',
            'address' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
        }

        $branch->update($request->only('name', 'address'));

        return response()->json(['status' => true, 'message' => 'Branch updated.', 'data' => $branch]);
    }

    public function destroy($id)
    {
        $branch = Branch::find($id);
        if (!$branch) {
            return response()->json(['status' => false, 'message' => 'Branch not found.'], 404);
        }

        $branch->delete();

        return response()->json(['status' => true, 'message' => 'Branch deleted.']);
    }
}
