<?php

namespace App\Http\Controllers;

use App\Models\IntentionToAward;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Cloudinary\Cloudinary;
use Exception;

class IntentionToAwardController extends Controller
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

    // List all intentions
    public function index()
    {
        $intentions = IntentionToAward::with([
            'user:user_id,name',
            'tender:tender_id,title'
        ])->orderBy('intention_id', 'desc')->get();

        return response()->json([
            'status' => true,
            'message' => 'Intentions fetched successfully.',
            'data' => $intentions
        ], 200);
    }



    public function loggedUserIntention(Request $request)
{
    try {
        $userId = Auth::id(); 

        if (!$userId) {
            return response()->json([
                'status' => false,
                'message' => 'User not authenticated.',
            ], 401);
        }

        $intentions = IntentionToAward::with([
            'user:user_id,name',
            'tender:tender_id,title'
        ])
        ->where('user_id', $userId) // Filter by logged-in user's ID
        ->orderBy('intention_id', 'desc')
        ->get();

        if ($intentions->isEmpty()) {
            return response()->json([
                'status' => true,
                'message' => 'No intentions found for the logged-in user.',
                'data' => []
            ], 200);
        }

        return response()->json([
            'status' => true,
            'message' => 'Intentions fetched successfully for the logged-in user.',
            'data' => $intentions
        ], 200);
    } catch (Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to fetch intentions.',
            'error' => $e->getMessage()
        ], 500);
    }
}

    // Store a new intention
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tender_id' => 'required|exists:tenders,tender_id',
            'intention_file' => 'required|file|mimes:pdf|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors.',
                'errors' => $validator->errors()
            ], 400);
        }

        $response = [
            'intention' => ['status' => false, 'message' => '', 'data' => null],
            'email' => ['status' => false, 'message' => '', 'details' => []],
        ];

        try {
            // Upload file to Cloudinary
            $intentionFileUrl = $this->uploadIntentionFile($request);

            // Create intention record
            $intention = IntentionToAward::create([
                'user_id' => Auth::id(),
                'tender_id' => $request->tender_id,
                'intention_file' => $intentionFileUrl,
            ]);

            $response['intention'] = [
                'status' => true,
                'message' => 'Intention to award created successfully.',
                'data' => $intention
            ];

            // Notify users with role_id = 1
            $users = User::where('role_id', 1)->get();
            $emailResults = [];

            if ($users->isEmpty()) {
                $response['email'] = [
                    'status' => false,
                    'message' => 'No users with role_id = 1 found to notify.',
                    'details' => []
                ];
            } else {
                foreach ($users as $user) {
                    $emailResult = $this->sendIntentionNotification($user, $intention);
                    $emailResults[] = [
                        'email' => $user->email,
                        'status' => $emailResult['status'],
                        'message' => $emailResult['message']
                    ];
                }

                $allEmailsSent = !in_array(false, array_column($emailResults, 'status'));
                $response['email'] = [
                    'status' => $allEmailsSent,
                    'message' => $allEmailsSent ? 'All notifications sent successfully.' : 'Some notifications failed.',
                    'details' => $emailResults
                ];
            }

            return response()->json([
                'status' => $response['intention']['status'] && $response['email']['status'],
                'message' => 'Intention creation and email notifications processed.',
                'results' => $response
            ], $response['intention']['status'] ? 201 : 500);

        } catch (Exception $e) {
            $response['intention'] = [
                'status' => false,
                'message' => 'Failed to create intention.',
                'data' => null,
                'error' => $e->getMessage()
            ];

            return response()->json([
                'status' => false,
                'message' => 'Intention creation failed.',
                'results' => $response
            ], 500);
        }
    }

    // Show a specific intention
    public function show($intention_id)
    {
        try {
            $intention = IntentionToAward::with([
                'user:user_id,name',
                'tender:tender_id,title'
            ])->where('intention_id', $intention_id)->firstOrFail();

            return response()->json([
                'status' => true,
                'message' => 'Intention fetched successfully.',
                'data' => $intention
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Intention not found.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    // Update an existing intention
    public function update(Request $request, $intention_id)
    {
        $validator = Validator::make($request->all(), [
            'tender_id' => 'sometimes|exists:tenders,tender_id',
            'intention_file' => 'sometimes|file|mimes:pdf|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors.',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $intention = IntentionToAward::findOrFail($intention_id);

            $data = [];
            if ($request->has('tender_id')) {
                $data['tender_id'] = $request->tender_id;
            }
            if ($request->hasFile('intention_file')) {
                // Delete old file from Cloudinary if exists
                if ($intention->intention_file) {
                    $publicId = $this->getPublicIdFromUrl($intention->intention_file);
                    $this->cloudinary->uploadApi()->destroy($publicId);
                    \Log::info("Deleted old Cloudinary file for intention_id: {$intention_id}", ['public_id' => $publicId]);
                }
                $data['intention_file'] = $this->uploadIntentionFile($request);
            }

            $intention->update($data);

            return response()->json([
                'status' => true,
                'message' => 'Intention updated successfully.',
                'data' => $intention
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update intention.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Delete an intention
    public function destroy($intention_id)
    {
        try {
            $intention = IntentionToAward::findOrFail($intention_id);

            // Delete file from Cloudinary
            if ($intention->intention_file) {
                $publicId = $this->getPublicIdFromUrl($intention->intention_file);
                $this->cloudinary->uploadApi()->destroy($publicId);
                \Log::info("Deleted Cloudinary file for intention_id: {$intention_id}", ['public_id' => $publicId]);
            }

            $intention->delete();

            return response()->json([
                'status' => true, // Fixed typo: removed "malas"
                'message' => 'Intention deleted successfully.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete intention.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Helper: Upload file to Cloudinary
    private function uploadIntentionFile(Request $request)
    {
        if ($request->hasFile('intention_file')) {
            $file = $request->file('intention_file');
            $uploadResult = $this->cloudinary->uploadApi()->upload($file->getRealPath(), [
                'folder' => 'intention_files',
                'resource_type' => 'auto',
            ]);
            \Log::info('Cloudinary Upload Result for Intention File:', (array) $uploadResult);
              return $uploadResult['secure_url'];
        }
        return null;
    }

    // Helper: Extract public ID from Cloudinary URL
    private function getPublicIdFromUrl($url)
    {
        $path = parse_url($url, PHP_URL_PATH);
        $parts = explode('/', $path);
        $publicIdWithExtension = end($parts);
        return pathinfo($publicIdWithExtension, PATHINFO_FILENAME); // Remove extension
    }

    // Helper: Send notification
    private function sendIntentionNotification(User $user, IntentionToAward $intention)
    {
        $tender = $intention->tender;
        $subject = "New Intention to Award: {$tender->title}";
        $emailBody = "Dear {$user->name},\n\n"
            . "A new intention to award has been submitted for tender: {$tender->title}.\n"
            . "Intention File: {$intention->intention_file}\n"
            . "Please review it on the portal.\n\n"
            . "Submitted by tender department";

        try {
            Mail::raw($emailBody, function ($message) use ($user, $subject) {
                $message->to($user->email)->subject($subject);
            });
            \Log::info("Intention notification sent successfully to {$user->email} for intention_id: {$intention->intention_id}");
            return [
                'status' => true,
                'message' => "Notification sent to {$user->email}"
            ];
        } catch (Exception $e) {
            \Log::error("Failed to send intention notification to {$user->email} for intention_id: {$intention->intention_id}: " . $e->getMessage());
            return [
                'status' => false,
                'message' => "Failed to send notification to {$user->email}: " . $e->getMessage()
            ];
        }
    }



    public function IntentionReports(Request $request)
    {
        $from = $request->query('from');
        $to = $request->query('to');

        $query = IntentionToAward::with([
            'user:user_id,name',
            'tender:tender_id,title'
        ]);

        if ($from && $to) {
            $query->whereBetween('created_at', [$from, $to]);
        }

        $intentions = $query->orderBy('intention_id', 'desc')->get();

        return response()->json([
            'status' => true,
            'message' => 'Intention reports fetched successfully.',
            'data' => $intentions
        ], 200);
    }
}