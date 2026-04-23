<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\UpdateComment;
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

        // Optimize: Process uploads efficiently
        $updatePhotoUrl = null;
        $updateFileUrl = null;
        
        $hasPhoto = $request->hasFile('update_photo');
        $hasFile = $request->hasFile('update_file');
        
        // Process uploads sequentially but with optimizations
        if ($hasPhoto) {
            $updatePhotoUrl = $this->uploadPhoto($request);
        }
        
        if ($hasFile) {
            $updateFileUrl = $this->uploadFile($request);
        }

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

        // Optimize: Send email notification asynchronously (non-blocking)
        // Remove this for now to improve performance
        // $this->sendChatUpdateNotification($chat);

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

// Handle photo upload to Cloudinary (optimized)
private function uploadPhoto(Request $request)
{
    if ($request->hasFile('update_photo')) {
        $file = $request->file('update_photo');

        try {
            $uploadResult = $this->cloudinary->uploadApi()->upload($file->getRealPath(), [
                'folder' => 'chat_photos',
                'resource_type' => 'auto',
                'quality' => 'auto:good', // Optimize for faster upload
                'fetch_format' => 'auto', // Auto-convert to optimal format
            ]);

            return $uploadResult['secure_url'];
        } catch (\Exception $e) {
            \Log::error('Photo upload failed: ' . $e->getMessage());
            return null;
        }
    }

    return null;  // Return null if no file is uploaded
}

// Handle file upload to Cloudinary (optimized)
private function uploadFile(Request $request)
{
    if ($request->hasFile('update_file')) {
        $file = $request->file('update_file');

        try {
            $uploadResult = $this->cloudinary->uploadApi()->upload($file->getRealPath(), [
                'folder' => 'chat_files',
                'resource_type' => 'auto',
                'use_filename' => true, // Keep original filename for faster processing
            ]);

            return $uploadResult['secure_url'];
        } catch (\Exception $e) {
            \Log::error('File upload failed: ' . $e->getMessage());
            return null;
        }
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
        \Log::info('Fetching chat reports for dates: ' . $validatedData['from'] . ' to ' . $validatedData['to']);
        
        $query = Chat::with(['user:user_id,name'])
            ->whereDate('created_at', '>=', $validatedData['from'])
            ->whereDate('created_at', '<=', $validatedData['to']);

        // Fetch the chat reports
        $chatReports = $query->orderBy('created_at', 'desc')->get([
            'chat_id', // Use chat_id as the primary key
            'title',
            'description',
            'update_photo',
            'update_file',
            'created_at',
            'user_id'
        ]);

        \Log::info('Found ' . $chatReports->count() . ' chat reports');

        // If no chat reports are found, return success with empty array
        if ($chatReports->isEmpty()) {
            return response()->json([
                'status' => true,
                'message' => 'No updates found for the selected date range.',
                'data' => []
            ], 200);
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

    // Admin method to view all department updates with pagination
    public function adminAllUpdates(Request $request)
    {
        try {
            // Only admin can access this
            if (Auth::user()->role_id !== 1) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized. Admin access required.',
                ], 403);
            }

            // Get pagination parameters
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 10);
            
            // Validate per_page parameter
            $perPage = min(max($perPage, 5), 100); // Between 5 and 100 items per page

            // Get paginated chats with user details
            $chats = Chat::with('user:user_id,name')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            if ($chats->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No updates found.',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'All department updates retrieved successfully.',
                'data' => $chats->items(),
                'pagination' => [
                    'current_page' => $chats->currentPage(),
                    'last_page' => $chats->lastPage(),
                    'per_page' => $chats->perPage(),
                    'total' => $chats->total(),
                    'from' => $chats->firstItem(),
                    'to' => $chats->lastItem(),
                    'has_more_pages' => $chats->hasMorePages(),
                    'next_page_url' => $chats->nextPageUrl(),
                    'prev_page_url' => $chats->previousPageUrl(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch updates: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Department updates method - each department can see all updates
    public function departmentUpdates()
    {
        try {
            // Get all chats with user details for department users
            $chats = Chat::with('user:user_id,name')
                ->orderBy('created_at', 'desc')
                ->get();

            if ($chats->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No updates found.',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Department updates retrieved successfully.',
                'data' => $chats
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch updates: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Return daily update counts for a user (for heatmap).
     * Query params: user_id (optional, defaults to self), start (date), end (date)
     */
    public function heatmap(Request $request)
    {
        try {
            $userId = $request->query('user_id', Auth::id());
            $start = $request->query('start', Carbon::now()->subMonths(6)->startOfMonth()->toDateString());
            $end = $request->query('end', Carbon::now()->toDateString());

            // Validate range
            $startDate = Carbon::parse($start)->startOfDay();
            $endDate = Carbon::parse($end)->endOfDay();

            $rows = Chat::where('user_id', $userId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            $map = [];
            foreach ($rows as $row) {
                $map[$row->date] = (int) $row->count;
            }

            // Get the date of the user's very first update (for all-time performance %)
            $firstUpdate = Chat::where('user_id', $userId)->min('created_at');
            $firstUpdateDate = $firstUpdate ? Carbon::parse($firstUpdate)->toDateString() : null;

            return response()->json([
                'status' => 'success',
                'data' => $map,
                'start' => $start,
                'end' => $end,
                'first_update_date' => $firstUpdateDate,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Return full update entries for a user on a specific date.
     * Query params: user_id, date
     */
    public function heatmapByDay(Request $request)
    {
        try {
            $userId = $request->query('user_id', Auth::id());
            $date = $request->query('date', Carbon::today()->toDateString());

            $chats = Chat::with('user:user_id,name')
                ->where('user_id', $userId)
                ->whereDate('created_at', $date)
                ->orderBy('created_at', 'desc')
                ->get();

            $data = $chats->map(function ($chat) {
                return [
                    'chat_id' => $chat->chat_id,
                    'title' => $chat->title,
                    'description' => $chat->description,
                    'update_photo' => $chat->update_photo,
                    'created_at' => $chat->created_at,
                    'user' => ['user_id' => $chat->user->user_id ?? null, 'name' => $chat->user->name ?? null],
                    'comments' => $chat->load('comments.ceo:user_id,name')->comments->map(function ($c) {
                        return [
                            'id' => $c->id,
                            'comment' => $c->comment,
                            'ceo_name' => $c->ceo->name ?? 'CEO',
                            'created_at' => $c->created_at,
                        ];
                    }),
                ];
            });

            return response()->json(['status' => 'success', 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * CEO endpoint: summary of all users' update activity.
     * Performance % is calculated from each user's first-ever update.
     * Missed count is for the current calendar week (Mon→today) only.
     * Excludes admin (role 1) and CEO (role 7) users.
     */
    public function ceoPerformanceSummary(Request $request)
    {
        try {
            $today = Carbon::today();
            $todayStr = $today->toDateString();

            // Monday of current week → today
            $mondayThisWeek = $today->copy()->startOfWeek(Carbon::MONDAY);

            // Count weekdays Mon→today (inclusive)
            $weekdaysThisWeek = 0;
            $d = $mondayThisWeek->copy();
            while ($d->lte($today)) {
                if ($d->isWeekday()) $weekdaysThisWeek++;
                $d->addDay();
            }

            // Only non-admin, non-CEO users
            $users = User::select('user_id', 'name', 'email', 'role_id')
                ->whereNotIn('role_id', [1, 7])
                ->get();

            // All-time stats per user (no date filter)
            $allTimeStats = DB::table('chats')
                ->selectRaw('user_id, MIN(DATE(created_at)) as first_date, COUNT(*) as total, COUNT(DISTINCT DATE(created_at)) as active_days')
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');

            // This week's active days per user
            $weekStats = DB::table('chats')
                ->where('created_at', '>=', $mondayThisWeek->startOfDay())
                ->selectRaw('user_id, COUNT(DISTINCT DATE(created_at)) as active_this_week')
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');

            // Who posted today
            $postedToday = DB::table('chats')
                ->whereDate('created_at', $todayStr)
                ->pluck('user_id')
                ->unique()
                ->toArray();

            $summary = $users->map(function ($user) use ($allTimeStats, $weekStats, $postedToday, $weekdaysThisWeek, $today) {
                $s = $allTimeStats->get($user->user_id);
                $w = $weekStats->get($user->user_id);

                $total = $s ? (int)$s->total : 0;
                $activeDays = $s ? (int)$s->active_days : 0;
                $firstDate = $s ? $s->first_date : null;
                $activeThisWeek = $w ? (int)$w->active_this_week : 0;

                // Count working days from first update to today
                $workingDaysTotal = 0;
                if ($firstDate) {
                    $cur = Carbon::parse($firstDate);
                    while ($cur->lte($today)) {
                        if ($cur->isWeekday()) $workingDaysTotal++;
                        $cur->addDay();
                    }
                }

                return [
                    'user_id' => $user->user_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                    'total_updates' => $total,
                    'active_days' => $activeDays,
                    'working_days_total' => $workingDaysTotal,
                    'first_update_date' => $firstDate,
                    'missed_this_week' => max(0, $weekdaysThisWeek - $activeThisWeek),
                    'avg_per_day' => $workingDaysTotal > 0 ? round($total / $workingDaysTotal, 1) : 0,
                    'posted_today' => in_array($user->user_id, $postedToday),
                    'compliance_pct' => $workingDaysTotal > 0 ? round(($activeDays / $workingDaysTotal) * 100) : 0,
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $summary,
                'weekdays_this_week' => $weekdaysThisWeek,
                'missed_today' => $summary->filter(fn($u) => !$u['posted_today'])->values(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * CEO weekly chart: returns last 8 weeks of update counts for a given user.
     */
    public function ceoWeeklyPerformance(Request $request)
    {
        try {
            $userId = $request->query('user_id');
            if (!$userId) {
                return response()->json(['status' => 'error', 'message' => 'user_id required'], 422);
            }

            $weeks = 8;
            $today = Carbon::today();
            $startOfThisWeek = $today->copy()->startOfWeek(Carbon::MONDAY);
            $startDate = $startOfThisWeek->copy()->subWeeks($weeks - 1);

            $data = [];
            for ($i = 0; $i < $weeks; $i++) {
                $weekStart = $startDate->copy()->addWeeks($i);
                $weekEnd = $weekStart->copy()->endOfWeek(Carbon::FRIDAY);
                if ($weekEnd->gt($today)) $weekEnd = $today->copy();

                // Count working days in this week
                $workingDays = 0;
                $d = $weekStart->copy();
                while ($d->lte($weekEnd)) {
                    if ($d->isWeekday()) $workingDays++;
                    $d->addDay();
                }

                // Count updates posted on distinct days this week
                $activeDays = DB::table('chats')
                    ->where('user_id', $userId)
                    ->whereBetween('created_at', [$weekStart->startOfDay(), $weekEnd->endOfDay()])
                    ->selectRaw('COUNT(DISTINCT DATE(created_at)) as cnt')
                    ->value('cnt') ?? 0;

                $updateCount = DB::table('chats')
                    ->where('user_id', $userId)
                    ->whereBetween('created_at', [$weekStart->startOfDay(), $weekEnd->endOfDay()])
                    ->count();

                $data[] = [
                    'week_start' => $weekStart->toDateString(),
                    'week_label' => $weekStart->format('d M'),
                    'working_days' => $workingDays,
                    'active_days' => (int) $activeDays,
                    'update_count' => $updateCount,
                    'compliance_pct' => $workingDays > 0 ? round(($activeDays / $workingDays) * 100) : 0,
                ];
            }

            return response()->json(['status' => 'success', 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * CEO feed: all recent updates from all non-admin users,
     * with user info and CEO comments. Sorted newest first.
     */
    public function ceoAllUpdates(Request $request)
    {
        try {
            $perPage = min(max((int) $request->query('per_page', 30), 5), 100);

            $chats = Chat::with(['user:user_id,name,email,role_id', 'comments.ceo:user_id,name'])
                ->whereHas('user', function ($q) {
                    $q->whereNotIn('role_id', [1]);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $data = $chats->getCollection()->map(function ($chat) {
                return [
                    'chat_id'      => $chat->chat_id,
                    'title'        => $chat->title,
                    'description'  => $chat->description,
                    'update_photo' => $chat->update_photo,
                    'update_file'  => $chat->update_file,
                    'created_at'   => $chat->created_at,
                    'user' => [
                        'user_id' => $chat->user->user_id ?? null,
                        'name'    => $chat->user->name ?? null,
                        'email'   => $chat->user->email ?? null,
                    ],
                    'comments' => $chat->comments->map(function ($c) {
                        return [
                            'id'        => $c->id,
                            'comment'   => $c->comment,
                            'ceo_name'  => $c->ceo->name ?? 'CEO',
                            'created_at'=> $c->created_at,
                        ];
                    }),
                ];
            });

            return response()->json([
                'status' => 'success',
                'data'   => $data,
                'pagination' => [
                    'current_page' => $chats->currentPage(),
                    'last_page'    => $chats->lastPage(),
                    'per_page'     => $chats->perPage(),
                    'total'        => $chats->total(),
                    'has_more'     => $chats->hasMorePages(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * CEO adds a comment on a specific update (chat).
     */
    public function addCeoComment(Request $request)
    {
        try {
            $request->validate([
                'chat_id' => 'required|integer|exists:chats,chat_id',
                'comment' => 'required|string|max:2000',
            ]);

            $comment = UpdateComment::create([
                'chat_id' => $request->chat_id,
                'ceo_id' => Auth::id(),
                'comment' => $request->comment,
                'is_read' => false,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Comment added successfully.',
                'data' => $comment,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get unread CEO comments for the logged-in user (notifications).
     */
    public function myNotifications()
    {
        try {
            $notifications = UpdateComment::with(['ceo:user_id,name', 'chat:chat_id,title,created_at'])
                ->whereHas('chat', fn($q) => $q->where('user_id', Auth::id()))
                ->orderBy('created_at', 'desc')
                ->take(50)
                ->get()
                ->map(function ($n) {
                    return [
                        'id' => $n->id,
                        'comment' => $n->comment,
                        'ceo_name' => $n->ceo->name ?? 'CEO',
                        'update_title' => $n->chat->title ?? '',
                        'update_date' => $n->chat->created_at ? Carbon::parse($n->chat->created_at)->toDateString() : null,
                        'chat_id' => $n->chat_id,
                        'is_read' => (bool) $n->is_read,
                        'created_at' => $n->created_at,
                    ];
                });

            return response()->json(['status' => 'success', 'data' => $notifications]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Mark CEO comment notifications as read.
     */
    public function markNotificationsRead(Request $request)
    {
        try {
            $ids = $request->input('ids', []);
            if (!empty($ids)) {
                UpdateComment::whereIn('id', $ids)
                    ->whereHas('chat', fn($q) => $q->where('user_id', Auth::id()))
                    ->update(['is_read' => true]);
            } else {
                // Mark all
                UpdateComment::whereHas('chat', fn($q) => $q->where('user_id', Auth::id()))
                    ->update(['is_read' => true]);
            }
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
