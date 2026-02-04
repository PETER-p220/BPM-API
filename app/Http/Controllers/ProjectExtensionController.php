<?php

namespace App\Http\Controllers;

use App\Models\ProjectExtension;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Cloudinary\Cloudinary;
use Exception;
use Carbon\Carbon;

class ProjectExtensionController extends Controller
{
    protected $cloudinary;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => config('cloudinary.cloud.cloud_name'),
                'api_key' => config('cloudinary.cloud.api_key'),
                'api_secret' => config('cloudinary.cloud.api_secret'),
            ],
            'url' => [
                'secure' => true,
            ],
        ]);
    }

    // List all project extensions
    public function index()
    {
        $projectExtensions = ProjectExtension::with([
            'project:project_id,project_name,user_id,end_date',
        ])->orderBy('extension_id', 'desc')->get();

        return response()->json([
            'status' => true,
            'message' => 'Project extensions fetched successfully.',
            'data' => $projectExtensions
        ], 200);
    }

    // List project extensions for the logged-in user's projects
    public function loggedUserProjectExtension(Request $request)
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }

            $projectExtensions = ProjectExtension::with([
                'project:project_id,project_name,user_id,end_date'
            ])
            ->whereHas('project', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->orderBy('extension_id', 'desc')
            ->get();

            if ($projectExtensions->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'message' => 'No project extensions found for the logged-in user.',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'status' => true,
                'message' => 'Project extensions fetched successfully for the logged-in user.',
                'data' => $projectExtensions
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch project extensions.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Store a new project extension
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,project_id',
            'extended_date' => 'required|date|after:today',
            'extend_letter_file' => 'required|file|mimes:pdf|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors.',
                'errors' => $validator->errors()
            ], 400);
        }

        $response = [
            'project_extension' => ['status' => false, 'message' => '', 'data' => null],
            'email' => ['status' => false, 'message' => '', 'details' => []],
        ];

        try {
            $extendedDate = Carbon::parse($request->extended_date)->setTimezone('Africa/Nairobi');
            $letterFileUrl = $this->uploadExtendLetterFile($request);

            // Fetch the project and check end_date
            $project = Project::findOrFail($request->project_id);
            $originalEndDate = Carbon::parse($project->end_date);

            // Create the project extension
            $projectExtension = ProjectExtension::create([
                'project_id' => $request->project_id,
                'extended_date' => $extendedDate->toDateString(),
                'extend_letter_file' => $letterFileUrl,
                'created_at' => $extendedDate,
                'updated_at' => Carbon::now('Africa/Nairobi'),
            ]);

            // Update the project's end_date to the extended_date
            $project->update([
                'end_date' => $extendedDate->toDateString(),
            ]);

            $response['project_extension'] = [
                'status' => true,
                'message' => 'Project extension created successfully.',
                'data' => $projectExtension->load('project') // Include updated project data
            ];

            // Notify the project owner
            $projectOwner = User::find($project->user_id);
            $emailResults = [];

            if ($projectOwner) {
                $emailResult = $this->sendProjectExtensionNotification($projectOwner, $projectExtension, $originalEndDate);
                $emailResults[] = [
                    'email' => $projectOwner->email,
                    'status' => $emailResult['status'],
                    'message' => $emailResult['message']
                ];
            } else {
                $emailResults[] = [
                    'email' => null,
                    'status' => false,
                    'message' => 'Project owner not found or user_id is null in projects table.'
                ];
            }

            $allEmailsSent = !in_array(false, array_column($emailResults, 'status'));
            $response['email'] = [
                'status' => $allEmailsSent,
                'message' => $allEmailsSent ? 'All notifications sent successfully.' : 'Some notifications failed.',
                'details' => $emailResults
            ];

            return response()->json([
                'status' => $response['project_extension']['status'],
                'message' => 'Project extension creation and email notifications processed.',
                'results' => $response
            ], $response['project_extension']['status'] ? 201 : 500);

        } catch (Exception $e) {
            $response['project_extension'] = [
                'status' => false,
                'message' => 'Failed to create project extension.',
                'data' => null,
                'error' => $e->getMessage()
            ];

            return response()->json([
                'status' => false,
                'message' => 'Project extension creation failed.',
                'results' => $response
            ], 500);
        }
    }

    // Show a specific project extension
    public function show($extension_id)
    {
        try {
            $projectExtension = ProjectExtension::with([
                'project:project_id,project_name,user_id,end_date'
            ])->where('extension_id', $extension_id)->firstOrFail();

            return response()->json([
                'status' => true,
                'message' => 'Project extension fetched successfully.',
                'data' => $projectExtension
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Project extension not found.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    // Update an existing project extension
    public function update(Request $request, $extension_id)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,project_id',
            'extended_date' => 'required|date|after:today',
            'extend_letter_file' => 'required|file|mimes:pdf|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors.',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $projectExtension = ProjectExtension::findOrFail($extension_id);
            $extendedDate = Carbon::parse($request->extended_date)->setTimezone('Africa/Nairobi');
            $letterFileUrl = $this->uploadExtendLetterFile($request);

            $data = [
                'project_id' => $request->project_id,
                'extended_date' => $extendedDate->toDateString(),
                'extend_letter_file' => $letterFileUrl,
                'created_at' => $extendedDate,
                'updated_at' => Carbon::now('Africa/Nairobi'),
            ];

            $projectExtension->update($data);

            // Update the project's end_date
            $project = Project::findOrFail($request->project_id);
            $project->update([
                'end_date' => $extendedDate->toDateString(),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Project extension updated successfully.',
                'data' => $projectExtension->load('project')
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update project extension.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Delete a project extension
    public function destroy($extension_id)
    {
        try {
            $projectExtension = ProjectExtension::findOrFail($extension_id);

            if ($projectExtension->extend_letter_file) {
                $publicId = $this->getPublicIdFromUrl($projectExtension->extend_letter_file);
                $this->cloudinary->uploadApi()->destroy($publicId);
                \Log::info("Deleted old Cloudinary file for extension_id: {$extension_id}", ['public_id' => $publicId]);
            }

            $projectExtension->delete();

            return response()->json([
                'status' => true,
                'message' => 'Project extension deleted successfully.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete project extension.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Helper: Upload file to Cloudinary
    private function uploadExtendLetterFile(Request $request)
    {
        if ($request->hasFile('extend_letter_file')) {
            $file = $request->file('extend_letter_file');
            $uploadResult = $this->cloudinary->uploadApi()->upload($file->getRealPath(), [
                'folder' => 'extend_letter_files',
                'resource_type' => 'auto',
            ]);
            \Log::info('Cloudinary Upload Result for Extend Letter File:', (array) $uploadResult);
            return $uploadResult['secure_url'];
        }
        throw new Exception('Extend letter file is required.');
    }

    // Helper: Extract public ID from Cloudinary URL
    private function getPublicIdFromUrl($url)
    {
        $path = parse_url($url, PHP_URL_PATH);
        $parts = explode('/', $path);
        $publicIdWithExtension = end($parts);
        return pathinfo($publicIdWithExtension, PATHINFO_FILENAME);
    }

    // Helper: Send notification with original and new end_date
    private function sendProjectExtensionNotification(User $user, ProjectExtension $projectExtension, $originalEndDate)
    {
        $project = $projectExtension->project;
        $subject = "Project Extension: {$project->project_name}";
        $emailBody = "Dear {$user->name},\n\n"
            . "Your project '{$project->project_name}' has been extended.\n"
            . "Original End Date: {$originalEndDate->format('Y-m-d')}\n"
            . "New End Date: {$project->end_date}\n" // Reflects the updated end_date
            . "Extension Letter File: {$projectExtension->extend_letter_file}\n"
            . "Please log in to review the details.\n\n"
            . "Thank you,\nTender Management System";

        try {
            Mail::raw($emailBody, function ($message) use ($user, $subject) {
                $message->to($user->email)->subject($subject);
            });
            \Log::info("Project extension notification sent successfully to {$user->email} for extension_id: {$projectExtension->extension_id}");
            return [
                'status' => true,
                'message' => "Notification sent to {$user->email}"
            ];
        } catch (Exception $e) {
            \Log::error("Failed to send project extension notification to {$user->email} for extension_id: {$projectExtension->extension_id}: " . $e->getMessage());
            return [
                'status' => false,
                'message' => "Failed to send notification to {$user->email}: " . $e->getMessage()
            ];
        }
    }
}