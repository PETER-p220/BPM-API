<?php

namespace App\Http\Controllers;

use App\Models\SecurityDeclaration;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Cloudinary\Cloudinary;
use Exception;

class SecurityDeclarationController extends Controller
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

    // List all security declarations
    public function index()
    {
        $securityDeclarations = SecurityDeclaration::with([
            'user:user_id,name',
            'tender:tender_id,title'
        ])->orderBy('declaration_id', 'desc')->get();

        return response()->json([
            'status' => true,
            'message' => 'Security declarations fetched successfully.',
            'data' => $securityDeclarations
        ], 200);
    }

    // List security declarations for the logged-in user
    public function loggedUserSecurityDeclaration(Request $request)
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }

            $securityDeclarations = SecurityDeclaration::with([
                'user:user_id,name',
                'tender:tender_id,title'
            ])
            ->where('user_id', $userId)
            ->orderBy('declaration_id', 'desc')
            ->get();

            if ($securityDeclarations->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'message' => 'No security declarations found for the logged-in user.',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'status' => true,
                'message' => 'Security declarations fetched successfully for the logged-in user.',
                'data' => $securityDeclarations
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch security declarations.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Store a new security declaration
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tender_id' => 'required|exists:tenders,tender_id',
            'declaration_file' => 'required|file|mimes:pdf|max:2048',
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
            'security_declaration' => ['status' => false, 'message' => '', 'data' => null],
            'email' => ['status' => false, 'message' => '', 'details' => []],
            'receiver_email' => ['status' => false, 'message' => '', 'details' => []],
        ];

        try {
            $declarationFileUrl = $this->uploadDeclarationFile($request);
            $securityDeclaration = SecurityDeclaration::create([
                'user_id' => Auth::id(),
                'tender_id' => $request->tender_id,
                'declaration_file' => $declarationFileUrl,
                'receiver_email' => $request->receiver_email,
            ]);

            $response['security_declaration'] = [
                'status' => true,
                'message' => 'Security declaration created successfully.',
                'data' => $securityDeclaration
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
                    $emailResult = $this->sendSecurityDeclarationNotification($user, $securityDeclaration);
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
                $receiverResult = $this->sendReceiverNotification($securityDeclaration, $request->receiver_email);
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
                'status' => $response['security_declaration']['status'] && $response['email']['status'] && $response['receiver_email']['status'],
                'message' => 'Security declaration creation and email notifications processed.',
                'results' => $response
            ], $response['security_declaration']['status'] ? 201 : 500);

        } catch (Exception $e) {
            $response['security_declaration'] = [
                'status' => false,
                'message' => 'Failed to create security declaration.',
                'data' => null,
                'error' => $e->getMessage()
            ];

            return response()->json([
                'status' => false,
                'message' => 'Security declaration creation failed.',
                'results' => $response
            ], 500);
        }
    }

    // Show a specific security declaration
    public function show($declaration_id)
    {
        try {
            $securityDeclaration = SecurityDeclaration::with([
                'user:user_id,name',
                'tender:tender_id,title'
            ])->where('declaration_id', $declaration_id)->firstOrFail();

            return response()->json([
                'status' => true,
                'message' => 'Security declaration fetched successfully.',
                'data' => $securityDeclaration
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Security declaration not found.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    // Update an existing security declaration
    public function update(Request $request, $declaration_id)
    {
        $validator = Validator::make($request->all(), [
            'tender_id' => 'sometimes|exists:tenders,tender_id',
            'declaration_file' => 'sometimes|file|mimes:pdf|max:2048',
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
            $securityDeclaration = SecurityDeclaration::findOrFail($declaration_id);

            $data = [];
            if ($request->has('tender_id')) {
                $data['tender_id'] = $request->tender_id;
            }
            if ($request->hasFile('declaration_file')) {
                if ($securityDeclaration->declaration_file) {
                    $publicId = $this->getPublicIdFromUrl($securityDeclaration->declaration_file);
                    $this->cloudinary->uploadApi()->destroy($publicId);
                    \Log::info("Deleted old Cloudinary file for declaration_id: {$declaration_id}", ['public_id' => $publicId]);
                }
                $data['declaration_file'] = $this->uploadDeclarationFile($request);
            }
            if ($request->has('receiver_email')) {
                $data['receiver_email'] = $request->receiver_email;
            }

            $securityDeclaration->update($data);

            // Send to receiver_email if provided in update
            if ($request->has('receiver_email')) {
                $receiverResult = $this->sendReceiverNotification($securityDeclaration, $request->receiver_email);
                \Log::info("Receiver notification sent for declaration_id: {$declaration_id}", [
                    'receiver_email' => $request->receiver_email
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Security declaration updated successfully.',
                'data' => $securityDeclaration
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update security declaration.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Delete a security declaration
    public function destroy($declaration_id)
    {
        try {
            $securityDeclaration = SecurityDeclaration::findOrFail($declaration_id);

            if ($securityDeclaration->declaration_file) {
                $publicId = $this->getPublicIdFromUrl($securityDeclaration->declaration_file);
                $this->cloudinary->uploadApi()->destroy($publicId);
                \Log::info("Deleted Cloudinary file for declaration_id: {$declaration_id}", ['public_id' => $publicId]);
            }

            $securityDeclaration->delete();

            return response()->json([
                'status' => true,
                'message' => 'Security declaration deleted successfully.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete security declaration.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Helper: Upload file to Cloudinary
    private function uploadDeclarationFile(Request $request)
    {
        if ($request->hasFile('declaration_file')) {
            $file = $request->file('declaration_file');
            $uploadResult = $this->cloudinary->uploadApi()->upload($file->getRealPath(), [
                'folder' => 'declaration_files',
                'resource_type' => 'auto',
            ]);
            \Log::info('Cloudinary Upload Result for Declaration File:', (array) $uploadResult);
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
    private function sendSecurityDeclarationNotification(User $user, SecurityDeclaration $securityDeclaration)
    {
        $tender = $securityDeclaration->tender;
        $subject = "New Security Declaration: {$tender->title}";
        $emailBody = "Dear {$user->name},\n\n"
            . "A new security declaration has been submitted for tender: {$tender->title}.\n"
            . "Declaration File: {$securityDeclaration->declaration_file}\n"
            . "Please review it on the portal.\n\n"
            . "Thank you,\nTender Management System";

        try {
            Mail::raw($emailBody, function ($message) use ($user, $subject) {
                $message->to($user->email)->subject($subject);
            });
            \Log::info("Security declaration notification sent successfully to {$user->email} for declaration_id: {$securityDeclaration->declaration_id}");
            return [
                'status' => true,
                'message' => "Notification sent to {$user->email}"
            ];
        } catch (Exception $e) {
            \Log::error("Failed to send security declaration notification to {$user->email} for declaration_id: {$securityDeclaration->declaration_id}: " . $e->getMessage());
            return [
                'status' => false,
                'message' => "Failed to send notification to {$user->email}: " . $e->getMessage()
            ];
        }
    }

    // Helper: Send notification to receiver email
    private function sendReceiverNotification(SecurityDeclaration $securityDeclaration, $receiverEmail)
    {
        $tender = $securityDeclaration->tender;
        $subject = "Security Declaration for Tender: {$tender->title}";
        $emailBody = "Dear Sir/Madam,\n\n"
            . "Please find attached the security declaration for the tender: {$tender->title}.\n"
            . "Thank you for your attention.\n\n"
            . "Best regards,\n"
            . "Tera Technologies and Engineering Limited";

        try {
            Mail::raw($emailBody, function ($message) use ($receiverEmail, $subject, $securityDeclaration, $tender) {
                $message->to($receiverEmail)
                    ->subject($subject)
                    ->attach($securityDeclaration->declaration_file, [
                        'as' => "Security_Declaration_{$tender->title}.pdf",
                        'mime' => 'application/pdf',
                    ]);
            });
            \Log::info("Security declaration sent successfully to receiver {$receiverEmail} for declaration_id: {$securityDeclaration->declaration_id}");
            return [
                'status' => true,
                'message' => "Security declaration sent to {$receiverEmail}"
            ];
        } catch (Exception $e) {
            \Log::error("Failed to send security declaration to receiver {$receiverEmail} for declaration_id: {$securityDeclaration->declaration_id}: " . $e->getMessage());
            return [
                'status' => false,
                'message' => "Failed to send security declaration to {$receiverEmail}: " . $e->getMessage()
            ];
        }
    }

    // Generate security declaration reports
    public function DeclarationReports(Request $request)
    {
        $from = $request->query('from');
        $to = $request->query('to');

        $query = SecurityDeclaration::with([
            'user:user_id,name',
            'tender:tender_id,title'
        ]);

        if ($from && $to) {
            $query->whereBetween('created_at', [$from, $to]);
        }

        $securityDeclarations = $query->orderBy('declaration_id', 'desc')->get();

        return response()->json([
            'status' => true,
            'message' => 'Security declaration reports fetched successfully.',
            'data' => $securityDeclarations
        ], 200);
    }
}