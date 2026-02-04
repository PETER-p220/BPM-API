<?php

namespace App\Http\Controllers;

use App\Models\ProjectActivity;
use App\Models\User;
use App\Models\Tender;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Project;
use App\Models\Department;
use Illuminate\Support\Facades\Mail;
use Cloudinary\Cloudinary;
use Cloudinary\Transformation\Transformation;
use Illuminate\Http\Request;

class ProjectActivitiesController extends Controller
{
    protected $cloudinary;

    public function __construct()
    {
        // Initialize Cloudinary with credentials from config
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => config('cloudinary.cloud.cloud_name'),
                'api_key' => config('cloudinary.cloud.api_key'),
                'api_secret' => config('cloudinary.cloud.api_secret'),
            ],
            'url' => [
                'secure' => true, // Use HTTPS
            ],
        ]);
    }


      // Fetch activities based on `user_id`
       public function index()
{
    $userId = Auth::id(); // Logged-in user's ID

    // Fetch activities based on logged-in user's ID
    $activities = ProjectActivity::with(['project', 'department', 'user'])
        ->where('user_id', $userId) // Filter by logged-in user
        ->whereHas('project', function ($query) {
            // Only include activities where the related project's status is neither 'completed' nor 'cancelled'
            $query->whereNotIn('project_status', ['completed', 'cancelled']);
        })
        ->latest()
        ->get();

    // Fetch the creator's name based on the 'iscreated_by' field which should match 'users.user_id'
    foreach ($activities as $activity) {
        // Check if the 'iscreated_by' field matches a user in the 'users' table
        $creator = User::where('user_id', $activity->iscreated_by)->first(); // Use user_id to match

        // If a matching user is found, attach the creator's name to the activity
        if ($creator) {
            $activity->creator_name = $creator->name;
        } else {
            $activity->creator_name = null; // If no matching user found, set as null
        }
    }

    return response()->json([
        'status' => 'success',
        'activities' => $activities,
    ], 200);
}




    // Fetch activities based on `iscreated_by` for the logged-in user's department
     public function index1()
{
    $userId = Auth::id(); // Logged-in user's ID

    // Ensure the user is logged in
    if (!$userId) {
        return response()->json([
            'status' => 'error',
            'message' => 'User not logged in.',
        ], 401);
    }

    // Get activities where 'iscreated_by' matches the logged-in user
    $activities = ProjectActivity::with(['project', 'department'])
        ->where('iscreated_by', $userId)  // Only filter by the creator
        ->whereHas('project', function ($query) {
            // Only include activities where the related project's status is neither 'completed' nor 'cancelled'
            $query->whereNotIn('project_status', ['completed', 'cancelled']);
        })
        ->latest()  // Get the latest activities
        ->get();

    return response()->json([
        'status' => 'success',
        'activities' => $activities,
    ], 200);
}





  // Fetch all activities without filtering by logged-in user
public function index2()
{
    $activities = ProjectActivity::with(['project', 'department'])
        ->latest()
        ->get();

    return response()->json([
        'status' => 'success',
        'activities' => $activities,
    ], 200);
}



    public function store(Request $request)
{
    $validatedData = $request->validate([
        'activity_category' => 'required|string|max:255',
        'project_id' => 'required|exists:projects,project_id',
        'department_id' => 'required|exists:departments,department_id',
        'user_id' => 'required|exists:users,user_id', // Ensure a valid user_id is provided
        'description' => 'nullable|string',
        'activity_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5048',
        'activity_file' => 'nullable|mimes:pdf,xlsx,pptx,csv,docx|max:3024',
    ]);

    try {
        // Step 1: Initialize the uploaded images and file array
        $uploadedImages = $this->uploadImages($request);
        $uploadedFile = $this->uploadActivityFile($request);

        // Step 2: Handle photo URL assignment
        $photoUrl = $uploadedImages['activity_photo'] ?? null;
        $fileUrl = $uploadedFile ?? null;

        // Step 3: Assign the logged-in user's ID to `iscreated_by`
        $loggedInUserId = Auth::id();  // Automatically set to the logged-in user

        // Step 4: Create the Project Activity entry
        $activity = ProjectActivity::create([
            'activity_category' => $validatedData['activity_category'],
            'user_id' => $validatedData['user_id'], // Use the provided user_id
            'project_id' => $validatedData['project_id'],
            'department_id' => $validatedData['department_id'],
            'description' => $validatedData['description'],
            'iscreated_by' => $loggedInUserId, // Automatically set to the logged-in user
            'activity_photo' => $photoUrl,
            'activity_file' => $fileUrl,
        ]);

        // Step 5: Send email notification to the user assigned to the project (user_id)
        $user = User::find($validatedData['user_id']); // Get user by user_id

        if ($user) {
            $this->sendActivityCreatedEmail($user, $activity);
        }

        // Step 6: Return the success response
        return response()->json([
            'status' => 'success',
            'message' => 'Activity created successfully',
            'activity' => $activity,
        ], 201);
    } catch (\Exception $e) {
        Log::error('Activity creation failed: ' . $e->getMessage());
        return response()->json(['status' => 'error', 'message' => 'Failed to create activity'], 500);
    }
}



    private function uploadImages(Request $request)
    {
        $uploadedImages = [];

        // Handle each image upload field as you need
        $imageFields = ['activity_photo']; // Add other image fields if necessary
        foreach ($imageFields as $imageField) {
            if ($request->hasFile($imageField)) {
                $file = $request->file($imageField);

                // Upload to Cloudinary
                $uploadResult = $this->cloudinary->uploadApi()->upload($file->getRealPath(), [
                    'folder' => 'project_activities', // Optional: Set folder for organization
                ]);

                // Store the image URL
                $uploadedImages[$imageField] = $uploadResult['secure_url'];
            }
        }

        return $uploadedImages;
    }


     private function uploadActivityFile(Request $request)
{
    // Check if the file is uploaded
    if ($request->hasFile('activity_file')) {
        $file = $request->file('activity_file');

        // Generate a unique file name
        $fileName = time() . '_' . $file->getClientOriginalName();

        // Move the file to the public/activity_files directory
        $file->move(public_path('activity_files'), $fileName);

        // Return the file URL
        return url('activity_files/' . $fileName);
    }

    return null;
}


private function sendActivityCreatedEmail(User $user, ProjectActivity $activity)
{
    $subject = 'New Project Activity Uploaded: ' . $activity->activity_category;

    // Fetch the creator (the user who created the activity based on 'iscreated_by')
    $creator = User::find($activity->iscreated_by);
    $creatorName = $creator ? $creator->name : 'Unknown';  // Get creator's name or 'Unknown'

    // Fetch the assigned user by matching 'user_id' in the 'users' table
    $assignedUser = User::where('user_id', '=', $activity->user_id)->first();
    $assignedUserName = $assignedUser ? $assignedUser->name : 'Unknown'; // Get assigned user's name or 'Unknown'

    // Fetch the project name based on the project_id
    $project = Project::find($activity->project_id);  // Assuming 'Project' is the model for the projects table
    $projectName = $project ? $project->project_name : 'Unknown Project';  // Get project name or 'Unknown Project'

    // Fetch the department name based on the department_id
    $department = Department::find($activity->department_id);  // Assuming 'Department' is the model for the departments table
    $departmentName = $department ? $department->name : 'Unknown Department';  // Get department name or 'Unknown Department'

    // Prepare the email content
    $emailBody = "Dear {$assignedUserName},\n\n"
        . "A new activity has been uploaded for  project.\n"
        . "Activity Category: {$activity->activity_category}\n"
        . "Project Name: {$projectName}\n"  // Display the project name
        . "Department Name: {$departmentName}\n"  // Display the department name
        . "Description: {$activity->description}\n"
        . "Activity Photo: " . ($activity->activity_photo ? 'Available' : 'None') . "\n"
        . "Activity File: " . ($activity->activity_file ? 'Available' : 'None') . "\n\n"
        . "Created By: {$creatorName}\n"  // Creator's name from 'iscreated_by'
        . "Please check your portal for more details and take any necessary actions.\n\n"
        . "Thank you for your attention.";

    // Send the email to the assigned user
    try {
        Mail::raw($emailBody, function ($message) use ($assignedUser, $subject) {
            $message->to($assignedUser->email);
            $message->subject($subject);
        });
    } catch (\Exception $e) {
        \Log::error('Email sending failed: ' . $e->getMessage());
    }
}



public function update(Request $request, $activity_id)
{
    // Validate incoming data
    $validatedData = $request->validate([
        'activity_category' => 'nullable|string|max:255',
        'project_id' => 'nullable|exists:projects,project_id',
        'department_id' => 'nullable|exists:departments,department_id',
        'user_id' => 'nullable|exists:users,user_id',
        'description' => 'nullable|string',
        'task_status' => 'nullable|in:on-progress,pending,completed',
        'is_viewed' => 'nullable|in:viewed,pending',
    ]);

    try {
        // Find the activity to update
        $activity = ProjectActivity::findOrFail($activity_id);

        // Update only the fields that were provided
        $activity->fill($validatedData);

        // Save the updated activity
        $activity->save();

        // Return the success response
        return response()->json([
            'status' => 'success',
            'message' => 'Activity updated successfully',
            'activity' => $activity, // Return updated activity data
        ], 200);
    } catch (\Exception $e) {
        Log::error('Activity update failed: ' . $e->getMessage());
        return response()->json(['status' => 'error', 'message' => 'Failed to update activity'], 500);
    }
}






public function show($activity_id)
{
    $activity = ProjectActivity::with(['project', 'department'])
        ->where('activity_id', $activity_id)
        ->first();

    if (!$activity) {
        return response()->json(['status' => 'error', 'message' => 'Activity not found'], 404);
    }

    return response()->json([
        'status' => 'success',
        'activity' => $activity
    ], 200);
}



    // DESTROY: Delete an activity
    public function destroy($activity_id)
    {
        $activity = ProjectActivity::where('activity_id', $activity_id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$activity) {
            return response()->json(['status' => 'error', 'message' => 'Activity not found'], 404);
        }

        $activity->delete();

        return response()->json(['status' => 'success', 'message' => 'Activity deleted successfully'], 200);
    }


    public function countAllActivities()
{
    try {
        // Count the total number of project activities
        $activityCount = ProjectActivity::count();

        return response()->json([
            'status' => 'success',
            'activity_count' => $activityCount,
        ], 200);
    } catch (\Exception $e) {
        Log::error('Failed to count activities: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to count activities',
        ], 500);
    }
}


public function countUserActivities()
{
    $userId = Auth::id(); // Get logged-in user's ID

    // Count activities that belong to the logged-in user and are not in completed or cancelled projects
    $count = ProjectActivity::where('user_id', $userId)
        ->whereHas('project', function ($query) {
            $query->whereNotIn('project_status', ['completed', 'cancelled']);
        })
        ->count();

    return response()->json([
        'status' => 'success',
        'count' => $count
    ]);
}


public function countUserV1Projects()
{
    $userId = Auth::id(); // Get logged-in user's ID

    // Count projects that are created by the logged-in user and are not in completed or cancelled status
    $count = ProjectActivity::where('iscreated_by', $userId)
        ->whereHas('project', function ($query) {
            $query->whereNotIn('project_status', ['completed', 'cancelled']);
        })
        ->count();

    return response()->json([
        'status' => 'success',
        'count' => $count
    ]);
}

}
