<?php

namespace App\Http\Controllers;

use App\Models\InsuranceBond;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Cloudinary\Cloudinary;
use Exception;

class InsuranceBondController extends Controller
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

    // List all insurance bonds
    public function index()
    {
        $insuranceBonds = InsuranceBond::with([
            'user:user_id,name',
            'tender:tender_id,title'
        ])->orderBy('insurance_id', 'desc')->get();

        return response()->json([
            'status' => true,
            'message' => 'Insurance bonds fetched successfully.',
            'data' => $insuranceBonds
        ], 200);
    }

    // List insurance bonds for the logged-in user
    public function loggedUserInsuranceBond(Request $request)
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }

            $insuranceBonds = InsuranceBond::with([
                'user:user_id,name',
                'tender:tender_id,title'
            ])
            ->where('user_id', $userId)
            ->orderBy('insurance_id', 'desc')
            ->get();

            if ($insuranceBonds->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'message' => 'No insurance bonds found for the logged-in user.',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'status' => true,
                'message' => 'Insurance bonds fetched successfully for the logged-in user.',
                'data' => $insuranceBonds
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch insurance bonds.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Store a new insurance bond
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tender_id' => 'required|exists:tenders,tender_id',
            'insurance_file' => 'required|file|mimes:pdf|max:2048',
            'receiver_email' => 'sometimes|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors.',
                'errors' => $validator->errors()
            ], 400);
        }

        $response = [
            'insurance_bond' => ['status' => false, 'message' => '', 'data' => null],
            'email' => ['status' => false, 'message' => '', 'details' => []],
            'receiver_email' => ['status' => false, 'message' => '', 'details' => []],
        ];

        try {
            $insuranceFileUrl = $this->uploadInsuranceFile($request);
            $insuranceBond = InsuranceBond::create([
                'user_id' => Auth::id(),
                'tender_id' => $request->tender_id,
                'insurance_file' => $insuranceFileUrl,
                'receiver_email' => $request->receiver_email,
            ]);

            $response['insurance_bond'] = [
                'status' => true,
                'message' => 'Insurance bond created successfully.',
                'data' => $insuranceBond
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
                    $emailResult = $this->sendInsuranceBondNotification($user, $insuranceBond);
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

            // Send to receiver_email if provided
            if ($request->has('receiver_email')) {
                $receiverResult = $this->sendReceiverNotification($insuranceBond, $request->receiver_email);
                $response['receiver_email'] = [
                    'status' => $receiverResult['status'],
                    'message' => $receiverResult['message'],
                    'details' => [
                        'email' => $request->receiver_email,
                        'status' => $receiverResult['status'],
                        'message' => $receiverResult['message']
                    ]
                ];
            } else {
                $response['receiver_email'] = [
                    'status' => true,
                    'message' => 'No receiver email provided.',
                    'details' => []
                ];
            }

            return response()->json([
                'status' => $response['insurance_bond']['status'] && $response['email']['status'] && $response['receiver_email']['status'],
                'message' => 'Insurance bond creation and email notifications processed.',
                'results' => $response
            ], $response['insurance_bond']['status'] ? 201 : 500);

        } catch (Exception $e) {
            $response['insurance_bond'] = [
                'status' => false,
                'message' => 'Failed to create insurance bond.',
                'data' => null,
                'error' => $e->getMessage()
            ];

            return response()->json([
                'status' => false,
                'message' => 'Insurance bond creation failed.',
                'results' => $response
            ], 500);
        }
    }

    // Show a specific insurance bond
    public function show($insurance_id)
    {
        try {
            $insuranceBond = InsuranceBond::with([
                'user:user_id,name',
                'tender:tender_id,title'
            ])->where('insurance_id', $insurance_id)->firstOrFail();

            return response()->json([
                'status' => true,
                'message' => 'Insurance bond fetched successfully.',
                'data' => $insuranceBond
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Insurance bond not found.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    // Update an existing insurance bond
    public function update(Request $request, $insurance_id)
    {
        $validator = Validator::make($request->all(), [
            'tender_id' => 'sometimes|exists:tenders,tender_id',
            'insurance_file' => 'sometimes|file|mimes:pdf|max:2048',
            'receiver_email' => 'sometimes|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors.',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $insuranceBond = InsuranceBond::findOrFail($insurance_id);

            $data = [];
            if ($request->has('tender_id')) {
                $data['tender_id'] = $request->tender_id;
            }
            if ($request->hasFile('insurance_file')) {
                if ($insuranceBond->insurance_file) {
                    $publicId = $this->getPublicIdFromUrl($insuranceBond->insurance_file);
                    $this->cloudinary->uploadApi()->destroy($publicId);
                    \Log::info("Deleted old Cloudinary file for insurance_id: {$insurance_id}", ['public_id' => $publicId]);
                }
                $data['insurance_file'] = $this->uploadInsuranceFile($request);
            }
            if ($request->has('receiver_email')) {
                $data['receiver_email'] = $request->receiver_email;
            }

            $insuranceBond->update($data);

            // Send to receiver_email if provided in update
            if ($request->has('receiver_email')) {
                $receiverResult = $this->sendReceiverNotification($insuranceBond, $request->receiver_email);
                \Log::info("Receiver notification sent for insurance_id: {$insurance_id}", [
                    'receiver_email' => $request->receiver_email
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Insurance bond updated successfully.',
                'data' => $insuranceBond
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update insurance bond.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Delete an insurance bond
    public function destroy($insurance_id)
    {
        try {
            $insuranceBond = InsuranceBond::findOrFail($insurance_id);

            if ($insuranceBond->insurance_file) {
                $publicId = $this->getPublicIdFromUrl($insuranceBond->insurance_file);
                $this->cloudinary->uploadApi()->destroy($publicId);
                \Log::info("Deleted Cloudinary file for insurance_id: {$insurance_id}", ['public_id' => $publicId]);
            }

            $insuranceBond->delete();

            return response()->json([
                'status' => true,
                'message' => 'Insurance bond deleted successfully.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete insurance bond.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Helper: Upload file to Cloudinary
    private function uploadInsuranceFile(Request $request)
    {
        if ($request->hasFile('insurance_file')) {
            $file = $request->file('insurance_file');
            $uploadResult = $this->cloudinary->uploadApi()->upload($file->getRealPath(), [
                'folder' => 'insurance_files',
                'resource_type' => 'auto',
            ]);
            \Log::info('Cloudinary Upload Result for Insurance File:', (array) $uploadResult);
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

    // Helper: Send notification to users with role_id = 1
    private function sendInsuranceBondNotification(User $user, InsuranceBond $insuranceBond)
    {
        $tender = $insuranceBond->tender;
        $subject = "New Insurance Bond: {$tender->title}";
        $emailBody = "Dear {$user->name},\n\n"
            . "A new insurance bond has been submitted for tender: {$tender->title}.\n"
            . "Insurance File: {$insuranceBond->insurance_file}\n"
            . "Please review it on the portal.\n\n"
            . "Submitted by tender department";

        try {
            Mail::raw($emailBody, function ($message) use ($user, $subject) {
                $message->to($user->email)->subject($subject);
            });
            \Log::info("Insurance bond notification sent successfully to {$user->email} for insurance_id: {$insuranceBond->insurance_id}");
            return [
                'status' => true,
                'message' => "Notification sent to {$user->email}"
            ];
        } catch (Exception $e) {
            \Log::error("Failed to send insurance bond notification to {$user->email} for insurance_id: {$insuranceBond->insurance_id}: " . $e->getMessage());
            return [
                'status' => false,
                'message' => "Failed to send notification to {$user->email}: " . $e->getMessage()
            ];
        }
    }

    // Helper: Send notification to receiver email
    private function sendReceiverNotification(InsuranceBond $insuranceBond, $receiverEmail)
    {
        $tender = $insuranceBond->tender;
        $subject = "Insurance Bond for Tender: {$tender->title}";
        $emailBody = "Dear Sir/Madam,\n\n"
            . "Please find attached the insurance bond for the tender: {$tender->title}.\n"
            . "Thank you for your attention.\n\n"
            . "Best regards,\n"
            . "Tera Technologies and Engineering Limited";

        try {
            Mail::raw($emailBody, function ($message) use ($receiverEmail, $subject, $insuranceBond, $tender) {
                $message->to($receiverEmail)
                    ->subject($subject)
                    ->attach($insuranceBond->insurance_file, [
                        'as' => "Insurance_Bond_{$tender->title}.pdf",
                        'mime' => 'application/pdf',
                    ]);
            });
            \Log::info("Insurance bond sent successfully to receiver {$receiverEmail} for insurance_id: {$insuranceBond->insurance_id}");
            return [
                'status' => true,
                'message' => "Insurance bond sent to {$receiverEmail}"
            ];
        } catch (Exception $e) {
            \Log::error("Failed to send insurance bond to receiver {$receiverEmail} for insurance_id: {$insuranceBond->insurance_id}: " . $e->getMessage());
            return [
                'status' => false,
                'message' => "Failed to send insurance bond to {$receiverEmail}: " . $e->getMessage()
            ];
        }
    }

    // Generate insurance bond reports
    public function InsBondReports(Request $request)
    {
        $from = $request->query('from');
        $to = $request->query('to');

        $query = InsuranceBond::with([
            'user:user_id,name',
            'tender:tender_id,title'
        ]);

        if ($from && $to) {
            $query->whereBetween('created_at', [$from, $to]);
        }

        $insuranceBonds = $query->orderBy('insurance_id', 'desc')->get();

        return response()->json([
            'status' => true,
            'message' => 'Insurance bond reports fetched successfully.',
            'data' => $insuranceBonds
        ], 200);
    }
}