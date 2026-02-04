<?php

namespace App\Http\Controllers;

use App\Models\AwardLetter;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Cloudinary\Cloudinary;
use Exception;

class AwardLetterController extends Controller
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

    // List all award letters
    public function index()
    {
        $awardLetters = AwardLetter::with([
            'user:user_id,name',
            'tender:tender_id,title'
        ])->orderBy('award_id', 'desc')->get();

        return response()->json([
            'status' => true,
            'message' => 'Award letters fetched successfully.',
            'data' => $awardLetters
        ], 200);
    }

    // List award letters for the logged-in user
    public function loggedUserAwardLetter(Request $request)
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }

            $awardLetters = AwardLetter::with([
                'user:user_id,name',
                'tender:tender_id,title'
            ])
            ->where('user_id', $userId)
            ->orderBy('award_id', 'desc')
            ->get();

            if ($awardLetters->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'message' => 'No award letters found for the logged-in user.',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'status' => true,
                'message' => 'Award letters fetched successfully for the logged-in user.',
                'data' => $awardLetters
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch award letters.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Store a new award letter
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tender_id' => 'required|exists:tenders,tender_id',
            'awardletter_file' => 'required|file|mimes:pdf|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors.',
                'errors' => $validator->errors()
            ], 400);
        }

        $response = [
            'award_letter' => ['status' => false, 'message' => '', 'data' => null],
            'email' => ['status' => false, 'message' => '', 'details' => []],
        ];

        try {
            $awardLetterFileUrl = $this->uploadAwardLetterFile($request);
            $awardLetter = AwardLetter::create([
                'user_id' => Auth::id(),
                'tender_id' => $request->tender_id,
                'awardletter_file' => $awardLetterFileUrl,
            ]);

            $response['award_letter'] = [
                'status' => true,
                'message' => 'Award letter created successfully.',
                'data' => $awardLetter
            ];

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
                    $emailResult = $this->sendAwardLetterNotification($user, $awardLetter);
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
                'status' => $response['award_letter']['status'] && $response['email']['status'],
                'message' => 'Award letter creation and email notifications processed.',
                'results' => $response
            ], $response['award_letter']['status'] ? 201 : 500);

        } catch (Exception $e) {
            $response['award_letter'] = [
                'status' => false,
                'message' => 'Failed to create award letter.',
                'data' => null,
                'error' => $e->getMessage()
            ];

            return response()->json([
                'status' => false,
                'message' => 'Award letter creation failed.',
                'results' => $response
            ], 500);
        }
    }

    // Show a specific award letter
    public function show($award_id)
    {
        try {
            $awardLetter = AwardLetter::with([
                'user:user_id,name',
                'tender:tender_id,title'
            ])->where('award_id', $award_id)->firstOrFail();

            return response()->json([
                'status' => true,
                'message' => 'Award letter fetched successfully.',
                'data' => $awardLetter
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Award letter not found.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    // Update an existing award letter
    public function update(Request $request, $award_id)
    {
        $validator = Validator::make($request->all(), [
            'tender_id' => 'sometimes|exists:tenders,tender_id',
            'awardletter_file' => 'sometimes|file|mimes:pdf|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors.',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $awardLetter = AwardLetter::findOrFail($award_id);

            $data = [];
            if ($request->has('tender_id')) {
                $data['tender_id'] = $request->tender_id;
            }
            if ($request->hasFile('awardletter_file')) {
                if ($awardLetter->awardletter_file) {
                    $publicId = $this->getPublicIdFromUrl($awardLetter->awardletter_file);
                    $this->cloudinary->uploadApi()->destroy($publicId);
                    \Log::info("Deleted old Cloudinary file for award_id: {$award_id}", ['public_id' => $publicId]);
                }
                $data['awardletter_file'] = $this->uploadAwardLetterFile($request);
            }

            $awardLetter->update($data);

            return response()->json([
                'status' => true,
                'message' => 'Award letter updated successfully.',
                'data' => $awardLetter
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update award letter.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Delete an award letter
    public function destroy($award_id)
    {
        try {
            $awardLetter = AwardLetter::findOrFail($award_id);

            if ($awardLetter->awardletter_file) {
                $publicId = $this->getPublicIdFromUrl($awardLetter->awardletter_file);
                $this->cloudinary->uploadApi()->destroy($publicId);
                \Log::info("Deleted Cloudinary file for award_id: {$award_id}", ['public_id' => $publicId]);
            }

            $awardLetter->delete();

            return response()->json([
                'status' => true,
                'message' => 'Award letter deleted successfully.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete award letter.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Helper: Upload file to Cloudinary
    private function uploadAwardLetterFile(Request $request)
    {
        if ($request->hasFile('awardletter_file')) {
            $file = $request->file('awardletter_file');
            $uploadResult = $this->cloudinary->uploadApi()->upload($file->getRealPath(), [
                'folder' => 'awardletter_files', // Updated folder name
                'resource_type' => 'auto',
            ]);
            \Log::info('Cloudinary Upload Result for Award Letter File:', (array) $uploadResult);
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
        return pathinfo($publicIdWithExtension, PATHINFO_FILENAME);
    }

    // Helper: Send notification
    private function sendAwardLetterNotification(User $user, AwardLetter $awardLetter)
    {
        $tender = $awardLetter->tender;
        $subject = "New Award Letter: {$tender->title}";
        $emailBody = "Dear {$user->name},\n\n"
            . "A new award letter has been submitted for tender: {$tender->title}.\n"
            . "Award Letter File: {$awardLetter->awardletter_file}\n"
            . "Please review it on the portal.\n\n"
            . "Submitted  by tender department";

        try {
            Mail::raw($emailBody, function ($message) use ($user, $subject) {
                $message->to($user->email)->subject($subject);
            });
            \Log::info("Award letter notification sent successfully to {$user->email} for award_id: {$awardLetter->award_id}");
            return [
                'status' => true,
                'message' => "Notification sent to {$user->email}"
            ];
        } catch (Exception $e) {
            \Log::error("Failed to send award letter notification to {$user->email} for award_id: {$awardLetter->award_id}: " . $e->getMessage());
            return [
                'status' => false,
                'message' => "Failed to send notification to {$user->email}: " . $e->getMessage()
            ];
        }
    }


    public function AwardsReports(Request $request)
    {
        $from = $request->query('from');
        $to = $request->query('to');

        $query = AwardLetter::with([
            'user:user_id,name',
            'tender:tender_id,title'
        ]);

        if ($from && $to) {
            $query->whereBetween('created_at', [$from, $to]);
        }

        $awardLetters = $query->orderBy('award_id', 'desc')->get();

        return response()->json([
            'status' => true,
            'message' => 'Award letter reports fetched successfully.',
            'data' => $awardLetters
        ], 200);
    }
}