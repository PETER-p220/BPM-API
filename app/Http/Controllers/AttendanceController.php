<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    // Get all attendance records
    public function index()
    {
        try {
            // Fetch all attendances along with the user data
            $attendances = Attendance::with('user')->get();

            return response()->json([
                'status' => 'success',
                'data' => $attendances,
            ], 200);
        } catch (\Exception $e) {
            // Log error and return error response
            Log::error('Failed to fetch attendances: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch attendances',
            ], 500);
        }
    }


     // Get attendance for the logged-in user
    public function myAttendance()
    {
        try {
            // Fetch attendance records for the logged-in user, along with user data
            $attendances = Attendance::where('user_id', Auth::id())->with('user')->get();

            // Check if there are any attendance records for the user
            if ($attendances->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No attendance records found for the user.',
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'data' => $attendances,
            ], 200);
        } catch (\Exception $e) {
            // Log error and return error response
            Log::error('Failed to fetch user attendance: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch your attendance',
            ], 500);
        }
    }
    

    // Get a single attendance record by its ID
    public function show($att_id)
    {
        try {
            $attendance = Attendance::findOrFail($att_id);
            return response()->json([
                'status' => 'success',
                'data' => $attendance,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to fetch attendance: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Attendance not found',
            ], 404);
        }
    }

    // Store a new attendance record
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'attenda_id' => 'required|exists:users,user_id',
            'is_present' => 'required|in:present,not-present',
            'if_not_present_and_have_reason' => 'nullable|string',
        ]);
    
        try {
            // Create new attendance entry
            $attendance = Attendance::create([
                'user_id' => Auth::id(),               // Current logged-in user
                'attenda_id' => $validatedData['attenda_id'],  // Attendee member ID
                'is_present' => $validatedData['is_present'],
                'if_not_present_and_have_reason' => $validatedData['if_not_present_and_have_reason'],
            ]);
    
            Log::info('Attendance created successfully for user_id: ' . Auth::id());
    
            return response()->json([
                'status' => 'success',
                'message' => 'Attendance created successfully',
                'data' => $attendance,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create attendance: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create attendance',
            ], 500);
        }
    }
    

    // Update an existing attendance record
    public function update(Request $request, $att_id)
    {
        $validatedData = $request->validate([
            'attenda_id' => 'required|exists:users,id',
            'is_present' => 'required|in:present,not-present',
            'if_not_present_and_have_reason' => 'nullable|string',
        ]);

        try {
            $attendance = Attendance::findOrFail($att_id);
            $attendance->update([
                'attenda_id' => $validatedData['attenda_id'],
                'is_present' => $validatedData['is_present'],
                'if_not_present_and_have_reason' => $validatedData['if_not_present_and_have_reason'],
            ]);

            Log::info('Attendance updated successfully for att_id: ' . $att_id);

            return response()->json([
                'status' => 'success',
                'message' => 'Attendance updated successfully',
                'data' => $attendance,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to update attendance: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update attendance',
            ], 500);
        }
    }

    // Delete an attendance record
    public function destroy($att_id)
    {
        try {
            $attendance = Attendance::findOrFail($att_id);
            $attendance->delete();

            Log::info('Attendance deleted successfully for att_id: ' . $att_id);

            return response()->json([
                'status' => 'success',
                'message' => 'Attendance deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to delete attendance: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete attendance',
            ], 500);
        }
    }


    // Fetch daily attendance report by created_at date
    public function fetchDailyReport(Request $request)
    {
        // Validate the date format
        $validatedData = $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);
    
        try {
            // Parse the date from the request and filter attendance based on that date
            $date = Carbon::parse($validatedData['date'])->startOfDay();
            $attendances = Attendance::whereDate('created_at', $date)->with('user')->get();
    
            // Return appropriate response if no data found
            if ($attendances->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No attendance records found for the selected date.',
                ], 200);
            }
    
            // Return the attendance data if found
            return response()->json([
                'status' => 'success',
                'data' => $attendances,
            ], 200);
        } catch (\Exception $e) {
            // Log the error and return error response
            Log::error('Failed to fetch daily report: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch the daily report.',
            ], 500);
        }
    }
    

    // Method to count all attendance records
public function countAllAttendances()
{
    try {
        // Get the total count of attendance records
        $count = Attendance::count();

        return response()->json([
            'status' => 'success',
            'data' => ['total_attendances' => $count],
        ], 200);
    } catch (\Exception $e) {
        // Log the error and return error response
        Log::error('Failed to count attendances: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to count attendances',
        ], 500);
    }
}

}
