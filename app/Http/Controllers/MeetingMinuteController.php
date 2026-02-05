<?php

namespace App\Http\Controllers;

use App\Models\MeetingMinute;
use App\Models\User;
use App\Models\Department;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;


class MeetingMinuteController extends Controller
{
    // Fetch all meeting minutes
    public function index()
    {
        try {
            $meetingMinutes = MeetingMinute::with([
                'user:user_id,name',          // Associated user data
                'department:department_id,name', // Associated department data,
                'project:project_id,project_name',
                'loggedUser:capture_logged_user_id,name'
            ])->orderBy('minutes_id', 'desc')->get();

            return response()->json([
                'status' => true,
                'data' => $meetingMinutes,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch meeting minutes.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



// Store a new meeting minute
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'minute_point' => 'required|array', // minute_point as an array
            'minute_point.*' => 'required|string', // Each point should be a string
            'user_id' => 'required|exists:users,user_id', // user_id must exist in the users table
            'department_id' => 'nullable|exists:departments,department_id', // department_id must exist in the departments table
            'if_more_detail' => 'nullable|string', // Optional additional detail
            'project_id' => 'required|exists:projects,project_id', // New: project_id must exist in the projects table
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // If if_more_detail is empty or not provided, set it to null
            $if_more_detail = $request->if_more_detail;
            if ($if_more_detail === null || $if_more_detail === '') {
                $if_more_detail = null;
            } else {
                $if_more_detail = json_encode([$if_more_detail]); // Encode as JSON array to match migration
            }

            // Join minute_point array into a single string
            $minute_point_string = implode(" | ", $request->minute_point); // Using '|' to separate points

            // Capture the logged-in user ID
            $capture_logged_user_id = Auth::id();

            // Store the combined minute point into a single row
            MeetingMinute::create([
                'user_id' => $request->user_id,
                'minute_point' => $minute_point_string, // Store all minute points in one string
                'if_more_detail' => $if_more_detail,    // Store if_more_detail
                'department_id' => $request->department_id,
                'project_id' => $request->project_id,   // Store project_id
                'capture_logged_user_id' => $capture_logged_user_id, // Store logged-in user ID
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Meeting minute(s) created successfully.',
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create meeting minute: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to create meeting minute.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    

    // Show a specific meeting minute
    public function show($id)
    {
        try {
            $minute = MeetingMinute::with([
                'user:user_id,name',
                'department:department_id,name',
                'project:project_id,project_name',
                'loggedUser:capture_logged_user_id,name'
            ])->findOrFail($id);

            return response()->json([
                'status' => true,
                'data' => $minute,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch the meeting minute.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Update a meeting minute
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'minute_point' => 'nullable|array',
            'minute_point.*' => 'nullable|string',
            'user_id' => 'nullable|exists:users,user_id',
            'department_id' => 'nullable|exists:departments,department_id',
            'if_more_detail' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $minute = MeetingMinute::findOrFail($id);

            // Update the fields if provided
            $minute->update([
                'minute_point' => $request->minute_point ?? $minute->minute_point,
                'user_id' => $request->user_id ?? $minute->user_id,
                'department_id' => $request->department_id ?? $minute->department_id,
                'if_more_detail' => $request->if_more_detail ?? $minute->if_more_detail,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Meeting minute updated successfully.',
                'data' => $minute,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update meeting minute.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Delete a meeting minute
    public function destroy($id)
    {
        try {
            $minute = MeetingMinute::findOrFail($id);
            $minute->delete();

            return response()->json([
                'status' => true,
                'message' => 'Meeting minute deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete meeting minute.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function fetchMeetingMinutesReport(Request $request)
    {
        // Validate the date format (expected in Y-m-d format)
        $validatedData = $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);
    
        try {
            // Parse the date from the request body and filter meeting minutes based on that date
            $date = Carbon::parse($validatedData['date'])->startOfDay();
    
            // Retrieve meeting minutes created on the given date
            $meetingMinutes = MeetingMinute::whereDate('created_at', $date)
                ->with(['user','project','loggedUser', 'department']) // Include related user and department data
                ->get();
    
            // Return appropriate response if no data found
            if ($meetingMinutes->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No meeting minutes found for the selected date.',
                ], 200);
            }
    
            // Return the meeting minutes data if found
            return response()->json([
                'status' => 'success',
                'data' => $meetingMinutes,
            ], 200);
        } catch (\Exception $e) {
            // Log the error and return error response
            Log::error('Failed to fetch meeting minutes report: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch the meeting minutes report.',
            ], 500);
        }
    }
    

    // Count total meeting minutes
public function countMeetingMinutes()
{
    try {
        $totalMeetingMinutes = MeetingMinute::count(); // Count the total number of meeting minutes

        return response()->json([
            'status' => true,
            'total_meeting_minutes' => $totalMeetingMinutes,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to count meeting minutes.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

}
