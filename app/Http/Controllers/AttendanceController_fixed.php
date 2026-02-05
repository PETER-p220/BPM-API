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
            // Fetch all attendances along with user data
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
                'message' => 'Failed to fetch user attendance',
            ], 500);
        }
    }

    // Store a new attendance record
    public function store(Request $request)
    {
        // Check if this is HR attendance (with meeting details) or accountant attendance
        $isHrAttendance = $request->has('meeting_date') || $request->has('meeting_type');
        
        if ($isHrAttendance) {
            // HR attendance validation
            $validatedData = $request->validate([
                'meeting_date' => 'required|date',
                'meeting_type' => 'required|string',
                'location' => 'nullable|string',
                'attendees' => 'required|string',
                'notes' => 'nullable|string',
            ]);
            
            // Create HR attendance entry
            $attendance = Attendance::create([
                'user_id' => Auth::id(),
                'meeting_date' => $validatedData['meeting_date'],
                'meeting_type' => $validatedData['meeting_type'],
                'location' => $validatedData['location'],
                'attendees' => $validatedData['attendees'],
                'notes' => $validatedData['notes'],
                'is_present' => 'present', // Default to present for HR
                'if_not_present_and_have_reason' => null,
            ]);
        } else {
            // Accountant attendance validation (original logic)
            $validatedData = $request->validate([
                'attenda_id' => 'required|exists:users,user_id',
                'is_present' => 'required|in:present,not-present',
                'if_not_present_and_have_reason' => 'nullable|string',
            ]);
            
            // Create accountant attendance entry
            $attendance = Attendance::create([
                'user_id' => Auth::id(),               // Current logged-in user
                'attenda_id' => $validatedData['attenda_id'],  // Attendee member ID
                'is_present' => $validatedData['is_present'],
                'if_not_present_and_have_reason' => $validatedData['if_not_present_and_have_reason'],
            ]);
        }

        try {
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

    // Show a specific attendance record
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

    // Delete an attendance record
    public function destroy($att_id)
    {
        try {
            $attendance = Attendance::findOrFail($att_id);
            $attendance->delete();
            
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
}
