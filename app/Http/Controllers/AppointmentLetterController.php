<?php

namespace App\Http\Controllers;

use App\Models\AppointmentLetter;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Cloudinary\Cloudinary;
use Exception;

class AppointmentLetterController extends Controller
{
    protected $cloudinary;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        try {
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
        } catch (\Cloudinary\Exception\ConfigurationException $e) {
            \Log::error('Cloudinary configuration failed: ' . $e->getMessage());
            $this->cloudinary = null;
        }
    }

    public function index()
    {
        try {
            $appointmentLetters = AppointmentLetter::with([
                'user:user_id,name',
                'tender:tender_id,title'
            ])->orderBy('letter_id', 'desc')->get();

            return response()->json([
                'status' => true,
                'message' => 'Appointment letters fetched successfully.',
                'data' => $appointmentLetters
            ], 200);
        } catch (Exception $e) {
            \Log::error('Error fetching appointment letters: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch appointment letters.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function loggedUserAppointmentLetter(Request $request)
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }

            $appointmentLetters = AppointmentLetter::with([
                'user:user_id,name',
                'tender:tender_id,title'
            ])
                ->where('user_id', $userId)
                ->orderBy('letter_id', 'desc')
                ->get();

            return response()->json([
                'status' => true,
                'message' => $appointmentLetters->isEmpty() ? 'No appointment letters found for the logged-in user.' : 'Appointment letters fetched successfully for the logged-in user.',
                'data' => $appointmentLetters
            ], 200);
        } catch (Exception $e) {
            \Log::error('Error fetching user appointment letters: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch appointment letters.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,user_id',
            'tender_id' => 'required|exists:tenders,tender_id',
            'letter_file' => 'required|file|mimes:pdf|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors.',
                'errors' => $validator->errors()
            ], 400);
        }

        $response = [
            'appointment_letter' => ['status' => false, 'message' => '', 'data' => null],
            'email' => ['status' => false, 'message' => '', 'details' => []],
        ];

        try {
            if (!$this->cloudinary) {
                throw new Exception('Cloudinary is not configured properly.');
            }

            $letterFileUrl = $this->uploadLetterFile($request);

            if (!$letterFileUrl) {
                throw new Exception('Failed to upload letter file.');
            }

            $appointmentLetter = AppointmentLetter::create([
                'user_id' => $request->user_id,
                'tender_id' => $request->tender_id,
                'letter_file' => $letterFileUrl,
            ]);

            $response['appointment_letter'] = [
                'status' => true,
                'message' => 'Appointment letter created successfully.',
                'data' => $appointmentLetter
            ];

            $loggedInUserId = Auth::id();
            $usersToNotify = User::where(function ($query) use ($request, $loggedInUserId) {
                $query->where('user_id', $request->user_id)
                      ->orWhere('role_id', 1);
            })
                ->where('user_id', '!=', $loggedInUserId)
                ->get();

            $emailResults = [];

            if ($usersToNotify->isEmpty()) {
                $response['email'] = [
                    'status' => false,
                    'message' => 'No users found to notify (excluding creator).',
                    'details' => []
                ];
            } else {
                foreach ($usersToNotify as $user) {
                    $emailResult = $this->sendAppointmentLetterNotification($user, $appointmentLetter);
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
                'status' => $response['appointment_letter']['status'] && $response['email']['status'],
                'message' => 'Appointment letter creation and email notifications processed.',
                'results' => $response
            ], $response['appointment_letter']['status'] ? 201 : 500);

        } catch (Exception $e) {
            \Log::error('Error creating appointment letter: ' . $e->getMessage());
            $response['appointment_letter'] = [
                'status' => false,
                'message' => 'Failed to create appointment letter.',
                'data' => null,
                'error' => $e->getMessage()
            ];

            return response()->json([
                'status' => false,
                'message' => 'Appointment letter creation failed.',
                'results' => $response
            ], 500);
        }
    }

    public function show($letter_id)
    {
        try {
            $appointmentLetter = AppointmentLetter::with([
                'user:user_id,name',
                'tender:tender_id,title'
            ])->where('letter_id', $letter_id)->firstOrFail();

            return response()->json([
                'status' => true,
                'message' => 'Appointment letter fetched successfully.',
                'data' => $appointmentLetter
            ], 200);
        } catch (Exception $e) {
            \Log::error('Error fetching appointment letter: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Appointment letter not found.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, $letter_id)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'sometimes|exists:users,user_id',
            'tender_id' => 'sometimes|exists:tenders,tender_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors.',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $appointmentLetter = AppointmentLetter::with(['user', 'tender'])->findOrFail($letter_id);

            $data = [];

            if ($request->has('user_id')) {
                $data['user_id'] = $request->user_id;
            }
            if ($request->has('tender_id')) {
                $data['tender_id'] = $request->tender_id;
            }

            $appointmentLetter->update($data);

            return response()->json([
                'status' => true,
                'message' => 'Appointment letter updated successfully.',
                'data' => $appointmentLetter
            ], 200);

        } catch (Exception $e) {
            \Log::error('Error updating appointment letter: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to update appointment letter.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accept an appointment letter
     */
    public function accept(Request $request, $letter_id)
    {
        try {
            $letter = AppointmentLetter::findOrFail($letter_id);
            
            // Verify the logged-in user is the assigned engineer
            $currentUserId = Auth::id();
            
            if ($letter->user_id !== $currentUserId) {
                return response()->json([
                    'status' => false,
                    'message' => 'You are not authorized to accept this appointment letter.'
                ], 403);
            }

            $letter->update([
                'status' => 'accepted',
                'status_updated_at' => now()
            ]);

            // Notify admin about the acceptance
            $this->notifyAdminStatusChange($letter, 'accepted');

            return response()->json([
                'status' => true,
                'message' => 'Appointment letter accepted successfully.',
                'data' => $letter->fresh()
            ], 200);
        } catch (Exception $e) {
            \Log::error('Error accepting appointment letter: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to accept appointment letter.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject an appointment letter
     */
    public function reject(Request $request, $letter_id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string|min:5|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation errors.',
                    'errors' => $validator->errors()
                ], 400);
            }

            $letter = AppointmentLetter::findOrFail($letter_id);
            
            // Verify the logged-in user is the assigned engineer
            $currentUserId = Auth::id();
            
            if ($letter->user_id !== $currentUserId) {
                return response()->json([
                    'status' => false,
                    'message' => 'You are not authorized to reject this appointment letter.'
                ], 403);
            }
            $letter->update([
                'status' => 'rejected',
                'status_updated_at' => now(),
                'rejection_reason' => $request->rejection_reason
            ]);

            // Notify admin about rejection
            $this->notifyAdminStatusChange($letter, 'rejected');

            return response()->json([
                'status' => true,
                'message' => 'Appointment letter rejected successfully.',
                'data' => $letter->fresh()
            ], 200);
        } catch (Exception $e) {
            \Log::error('Error rejecting appointment letter: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to reject appointment letter.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notify admin about status changes
     */
    private function notifyAdminStatusChange($letter, $status)
    {
        try {
            $adminUsers = User::where('role_id', 1)->get(); // Admin users
            
            foreach ($adminUsers as $admin) {
                $emailData = [
                    'letter_id' => $letter->letter_id,
                    'tender_title' => $letter->tender->title ?? 'N/A',
                    'engineer_name' => $letter->user->name ?? 'N/A',
                    'engineer_email' => $letter->user->email ?? 'N/A',
                    'status' => $status,
                    'status_updated_at' => now()->format('Y-m-d H:i:s'),
                    'action' => $status === 'accepted' ? 'Accepted' : 'Rejected',
                    'rejection_reason' => $letter->rejection_reason ?? null
                ];

                $emailBody = "Appointment Letter {$status}\n\n"
                    . "Letter ID: {$emailData['letter_id']}\n"
                    . "Tender: {$emailData['tender_title']}\n"
                    . "Engineer: {$emailData['engineer_name']} ({$emailData['engineer_email']})\n"
                    . "Status Updated: {$emailData['status_updated_at']}\n";
                    
                if ($status === 'rejected' && $emailData['rejection_reason']) {
                    $emailBody .= "Rejection Reason: {$emailData['rejection_reason']}\n";
                }

                Mail::raw($emailBody, function ($message) use ($admin, $status) {
                    $message->to($admin->email)
                        ->subject($status === 'accepted' ? 
                            'Appointment Letter Accepted' : 
                            'Appointment Letter Rejected'
                        )
                        ->from(config('mail.from.address'), config('mail.from.name'));
                });
            }
        } catch (Exception $e) {
            \Log::error('Error notifying admin: ' . $e->getMessage());
        }
    }

    public function destroy($letter_id)    
    {
        try {
            $appointmentLetter = AppointmentLetter::findOrFail($letter_id);

            if ($appointmentLetter->letter_file && $this->cloudinary) {
                $publicId = $this->getPublicIdFromUrl($appointmentLetter->letter_file);
                $this->cloudinary->uploadApi()->destroy($publicId);
                \Log::info("Deleted Cloudinary file for letter_id: {$letter_id}", ['public_id' => $publicId]);
            }

            $appointmentLetter->delete();

            return response()->json([
                'status' => true,
                'message' => 'Appointment letter deleted successfully.'
            ], 200);
        } catch (Exception $e) {
            \Log::error('Error deleting appointment letter: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete appointment letter.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function uploadLetterFile(Request $request)
    {
        if ($request->hasFile('letter_file')) {
            try {
                $file = $request->file('letter_file');
                $uploadResult = $this->cloudinary->uploadApi()->upload($file->getRealPath(), [
                    'folder' => 'appointment_letter_files',
                    'resource_type' => 'auto',
                ]);
                \Log::info('Cloudinary Upload Result for Appointment Letter File:', (array) $uploadResult);
                return $uploadResult['secure_url'];
            } catch (Exception $e) {
                \Log::error('Cloudinary upload failed: ' . $e->getMessage());
                return null;
            }
        }
        return null;
    }

    private function getPublicIdFromUrl($url)
    {
        $path = parse_url($url, PHP_URL_PATH);
        $parts = explode('/', $path);
        $publicIdWithExtension = end($parts);
        return 'appointment_letter_files/' . pathinfo($publicIdWithExtension, PATHINFO_FILENAME);
    }

    private function sendAppointmentLetterNotification(User $user, AppointmentLetter $appointmentLetter)
    {
        $tender = $appointmentLetter->tender;
        $subject = "New Appointment Letter: {$tender->title}";
        $emailBody = $user->role_id == 1
            ? "Dear {$user->name},\n\n"
              . "A new appointment letter has been submitted for tender: {$tender->title}.\n"
              . "Letter File: {$appointmentLetter->letter_file}\n"
              . "Please log in to review it.\n\n"
              . "Thank you,\nTender Management System"
            : "Dear {$user->name},\n\n"
              . "Your appointment letter for tender: {$tender->title} has been submitted.\n"
              . "Letter File: {$appointmentLetter->letter_file}\n"
              . "Please await review.\n\n"
              . "Thank you,\nTender Management System";

        try {
            Mail::raw($emailBody, function ($message) use ($user, $subject) {
                $message->to($user->email)->subject($subject);
            });
            \Log::info("Appointment letter notification sent successfully to {$user->email} for letter_id: {$appointmentLetter->letter_id}");
            return [
                'status' => true,
                'message' => "Notification sent to {$user->email}"
            ];
        } catch (Exception $e) {
            \Log::error("Failed to send appointment letter notification to {$user->email} for letter_id: {$appointmentLetter->letter_id}: " . $e->getMessage());
            return [
                'status' => false,
                'message' => "Failed to send notification to {$user->email}: " . $e->getMessage()
            ];
        }
    }
}