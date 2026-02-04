<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Cloudinary\Cloudinary;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class ChatController extends Controller
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


// Fetch chats created by the logged-in user
public function MyChats()
{
    try {
        // Get chats created by the logged-in user, along with the user details
        $chats = Chat::with('user:user_id,name')
            ->where('user_id', Auth::id()) // Only fetch chats created by the logged-in user
            ->orderBy('created_at', 'desc')
            ->get();

        // Check if there are any chats
        if ($chats->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No chats found for the logged-in user.',
            ], 404);
        }

        // Transform the chats to include file paths and user information
        $transformedChats = $chats->map(function ($chat) {
            return [
                'chat_id' => $chat->chat_id,
                'title' => $chat->title,
                'description' => $chat->description,
                'update_photo' => $chat->update_photo, // Assuming photo is stored in Cloudinary
                'update_file' => $chat->update_file
                    ? url('update_files/' . basename($chat->update_file)) // Fixing the URL
                    : null,
                'created_at' => $chat->created_at,
                'user' => [
                    'user_id' => $chat->user->user_id ?? null,
                    'name' => $chat->user->name ?? null,
                ]
            ];
        });

        // Return the fetched chats with the user data
        return response()->json([
            'status' => 'success',
            'message' => 'Chats retrieved successfully.',
            'data' => $transformedChats,
        ], 200);

    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to fetch chats.',
            'error' => $e->getMessage(),
        ], 500);
    }
}



    // Get all chat updates created within the last 24 hours
    public function index()
{
    try {
        // Fetch chat updates created within the last 24 hours, along with the related user details
        $chats = Chat::with('user:user_id,name')
            ->where('created_at', '>=', Carbon::now()->subDay()) // Filter chats from the last 24 hours
            ->orderBy('created_at', 'desc')
            ->get();

        // Transform the chats to include file paths and user information
        $transformedChats = $chats->map(function ($chat) {
            return [
                'chat_id' => $chat->chat_id,
                'title' => $chat->title,
                'description' => $chat->description,
                'update_photo' => $chat->update_photo, // Assuming photo is stored in Cloudinary
                'update_file' => $chat->update_file
                    ? url('update_files/' . basename($chat->update_file)) // Fixing the URL
                    : null,
                'created_at' => $chat->created_at,
                'user' => [
                    'user_id' => $chat->user->user_id ?? null,
                    'name' => $chat->user->name ?? null,
                ]
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Chat updates retrieved successfully.',
            'data' => $transformedChats
        ], 200);
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to fetch chat updates.',
            'error' => $e->getMessage(),
        ], 500);
    }
}


// Show a single chat update by chat_id
public function show($chat_id)
{
    $chat = Chat::with('user')->where('chat_id', $chat_id)->first();

    if (!$chat) {
        return response()->json([
            'status' => 'error',
            'message' => 'Chat update not found',
        ], 404);
    }

    return response()->json([
        'status' => 'success',
        'message' => 'Chat update retrieved successfully',
        'data' => $chat, // Ensure 'data' is correctly set
    ], 200);
}




   
public function store(Request $request)
{
    try {
        // Validate the incoming request
        $validatedData = $request->validate([
            'titles' => 'required|array|min:1',
            'titles.*' => 'required|string|max:255',
            'description' => 'nullable|string',
            'update_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5048',
            'update_file' => 'nullable|mimes:pdf,xlsx,csv,docx|max:10240',
        ]);

        // Log validated data for debugging
        \Log::info('Validated data: ', $validatedData);

        // Proceed with upload and chat creation
        $updatePhotoUrl = $this->uploadPhoto($request); // Upload photo to Cloudinary
        $updateFileUrl = $this->uploadFile($request);   // Upload file to Cloudinary

        // Create the chat update
        $chat = Chat::create([
            'title' => implode(', ', $validatedData['titles']),
            'description' => $validatedData['description'] ?? null,
            'update_photo' => $updatePhotoUrl,
            'update_file' => $updateFileUrl,
            'user_id' => Auth::id(),
        ]);

        // Check if chat was created successfully
        if (!$chat) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create chat update',
            ], 500);
        }

        // Send email notifications (optional)
        $this->sendChatUpdateNotification($chat);

        return response()->json([
            'status' => 'success',
            'message' => 'Chat update created successfully',
            'data' => $chat,
        ], 201);
    } catch (ValidationException $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);
    } catch (\Exception $e) {
        \Log::error('Error during chat creation: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => 'An error occurred while creating chat update',
            'error' => $e->getMessage(),
        ], 500);
    }
}

// Handle photo upload to Cloudinary
private function uploadPhoto(Request $request)
{
    if ($request->hasFile('update_photo')) {
        $file = $request->file('update_photo');

        $uploadResult = $this->cloudinary->uploadApi()->upload($file->getRealPath(), [
            'folder' => 'chat_photos',
            'resource_type' => 'auto',
        ]);

        return $uploadResult['secure_url'];
    }

    return null;  // Return null if no file is uploaded
}

// Handle file upload to Cloudinary
private function uploadFile(Request $request)
{
    if ($request->hasFile('update_file')) {
        $file = $request->file('update_file');

        $uploadResult = $this->cloudinary->uploadApi()->upload($file->getRealPath(), [
            'folder' => 'chat_files',
            'resource_type' => 'auto',
        ]);

        return $uploadResult['secure_url'];
    }

    return null;  // Return null if no file is uploaded
}



    // Send email notifications
    private function sendChatUpdateNotification(Chat $chat)
    {
        // Fetch all user emails except the creator's
        $userEmails = User::where('user_id', '!=', Auth::id())->pluck('email')->toArray();

        $subject = 'New  Update Posted: ' . $chat->title;
        $emailBody = "A new chat update has been posted:\n\n"
            . "Title: {$chat->title}\n"
            . "Description: " . ($chat->description ?? 'No description provided.') . "\n\n"
            . "Please log in to view the update.\n\nThank you.";

        // Send email to all users
        foreach ($userEmails as $email) {
            try {
                Mail::raw($emailBody, function ($message) use ($email, $subject) {
                    $message->to($email);
                    $message->subject($subject);
                });
            } catch (\Exception $e) {
                \Log::error("Failed to send email to {$email}: " . $e->getMessage());
            }
        }
    }


    // Delete existing file (local or Cloudinary)
    private function deleteExistingFile($filePath, $isCloudinary = false)
    {
        if ($filePath) {
            if ($isCloudinary) {
                $publicId = pathinfo($filePath, PATHINFO_FILENAME);
                $this->cloudinary->uploadApi()->destroy($publicId);
            } else {
                $fullPath = public_path($filePath);
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            }
        }
    }

    // Update an existing chat update
public function update(Request $request, $id)
{
    try {
        // Validate the incoming request
        $validatedData = $request->validate([
            'title' => 'required|string|max:255', // Ensure title is a required string with a max length
            'description' => 'nullable|string',   // Description is optional
        ]);

        // Find the chat update by its ID
        $chat = Chat::find($id);

        if (!$chat) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chat update not found',
            ], 404);
        }

        // Update the title and description fields
        $chat->title = $validatedData['title'];
        $chat->description = $validatedData['description'] ?? $chat->description; // If no description, retain the current one
        $chat->save();

        // Return the updated chat
        return response()->json([
            'status' => 'success',
            'message' => 'Chat update successfully updated',
            'data' => $chat,
        ], 200);
    } catch (ValidationException $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => $e->errors(),  // Get the validation errors
        ], 422);
    } catch (\Exception $e) {
        // Handle any other exceptions
        \Log::error('Error during chat update: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => 'An error occurred while updating chat',
            'error' => $e->getMessage(),
        ], 500);
    }
}


// Count all chats
public function countAllChats()
{
    try {
        // Count the total number of chats in the database
        $chatCount = Chat::count();

        return response()->json([
            'status' => 'success',
            'message' => 'Total number of chats retrieved successfully.',
            'data' => [
                'updates_count' => $chatCount
            ]
        ], 200);
    } catch (\Exception $e) {
        // Handle any exceptions
        \Log::error('Error during counting chats: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => 'An error occurred while counting the chats.',
            'error' => $e->getMessage(),
        ], 500);
    }
}



public function getChatReports(Request $request)
{
    try {
        // Validate request data using query parameters
        $validatedData = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        // Build the query to fetch chat reports
        $query = Chat::with(['user:user_id,name'])
            ->whereBetween(DB::raw("DATE(created_at)"), [$validatedData['from'], $validatedData['to']]);

        // Fetch the chat reports
        $chatReports = $query->orderBy('created_at', 'desc')->get([
            'chat_id', // Use chat_id as the primary key for the ChatReport
            'title',
            'description',
            'update_photo',
            'update_file',
            'created_at',
            'user_id'
        ]);

        // If no chat reports are found, return an empty response
        if ($chatReports->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No chat reports found.',
                'error' => 'No query results for model [App\\Models\\ChatReport].'
            ], 404);
        }

        // Format the response
        $formattedChatReports = $chatReports->map(function ($chatReport) {
            return [
                'chat_id' => $chatReport->chat_id, // Use chat_id in the response
                'title' => $chatReport->title,
                'description' => $chatReport->description,
                'update_photo' => $chatReport->update_photo,
                'update_file' => $chatReport->update_file,
                'created_at' => Carbon::parse($chatReport->created_at)->toIso8601String(),
                'user' => [
                    'user_id' => $chatReport->user->user_id,
                    'name' => $chatReport->user->name,
                ]
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Chat reports fetched successfully.',
            'data' => $formattedChatReports
        ], 200);

    } catch (ValidationException $e) {
        return response()->json([
            'status' => false,
            'message' => 'Validation failed.',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        \Log::error('Error fetching chat reports: ' . $e->getMessage());
        return response()->json([
            'status' => false,
            'message' => 'An error occurred while fetching the report.',
            'error' => $e->getMessage()
        ], 500);
    }
}

}
