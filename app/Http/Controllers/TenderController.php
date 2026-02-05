<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use App\Models\AssignTender;
use App\Models\Tender;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Cloudinary\Cloudinary;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TenderController extends Controller
{
    protected $cloudinary;

    public function __construct()
    {
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

    public function index()
    {
        try {
            $tenders = Tender::join('users', 'tenders.user_id', '=', 'users.user_id')
                ->select('tenders.*', 'users.name as user_name')
                ->orderBy('tenders.tender_id', 'desc')
                ->get();
    
            return response()->json([
                'status' => true,
                'message' => 'Tenders fetched successfully.',
                'data' => $tenders
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch tenders.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function tenderDropDown()
    {
        try {
            $tenders = Tender::select('tender_id', 'title')
                ->orderBy('tender_id', 'desc')
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Tenders fetched successfully.',
                'data' => $tenders
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch tenders.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'title' => 'required|string|max:255',
                'procurement_entity' => 'required|string|max:255',
                'tender_number' => 'required|string|max:100',
                'attachment' => 'nullable|mimes:pdf,doc,docx|max:10048',
                'tender_type' => 'required|string',
                'tender_source' => 'nullable|string',
                'date_of_Publication' => 'required|date',
                'expired_at' => 'required|date',
                'bid_submission' => 'required|date',
            ]);

            \Log::info('Validated tender data: ', $validatedData);

            $attachmentUrl = $this->uploadTenderAttachment($request);

            $tender = Tender::create([
                'title' => $validatedData['title'],
                'procurement_entity' => $validatedData['procurement_entity'],
                'tender_number' => $validatedData['tender_number'],
                'attachment' => $attachmentUrl,
                'tender_type' => $validatedData['tender_type'],
                'tender_source' => $validatedData['tender_source'],
                'date_of_Publication' => $validatedData['date_of_Publication'],
                'expired_at' => $validatedData['expired_at'],
                'bid_submission' => $validatedData['bid_submission'],
                'user_id' => Auth::id(),
            ]);

            if (!$tender) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to create tender',
                ], 500);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Tender created successfully',
                'data' => $tender,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error during tender creation: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while creating tender',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function uploadTenderAttachment(Request $request)
    {
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $uploadResult = $this->cloudinary->uploadApi()->upload($file->getRealPath(), [
                'folder' => 'tender_attachments',
                'resource_type' => 'auto',
            ]);

            \Log::info('Cloudinary Upload Result:', (array) $uploadResult);
            return $uploadResult['secure_url'];
        }
        return null;
    }

    public function show(string $tender_id)
    {
        try {
            $tender = Tender::where('tender_id', $tender_id)->firstOrFail();
            return response()->json([
                'status' => true,
                'message' => 'Tender retrieved successfully.',
                'data' => $tender
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Tender not found.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $tender = Tender::findOrFail($id);

            $validatedData = $request->validate([
                'title' => 'nullable|string|max:255',
                'procurement_entity' => 'nullable|string|max:255',
                'tender_number' => 'nullable|string|max:100',
                'attachment' => 'nullable|max:10048',
                'tender_type' => 'nullable|string',
                'tender_source' => 'nullable|string',
                'date_of_Publication' => 'nullable|date',
                'expired_at' => 'nullable|date',
                'bid_submission' => 'nullable|date',
            ]);

            if ($request->hasFile('attachment')) {
                $attachmentUrl = $this->uploadTenderAttachment($request);
                if ($tender->attachment) {
                    $this->deleteCloudinaryFile($tender->attachment);
                }
                $validatedData['attachment'] = $attachmentUrl;
            } else {
                $validatedData['attachment'] = $tender->attachment;
            }

            $tender->update([
                'title' => $validatedData['title'] ?? $tender->title,
                'procurement_entity' => $validatedData['procurement_entity'] ?? $tender->procurement_entity,
                'tender_number' => $validatedData['tender_number'] ?? $tender->tender_number,
                'attachment' => $validatedData['attachment'],
                'tender_type' => $validatedData['tender_type'] ?? $tender->tender_type,
                'tender_source' => $validatedData['tender_source'] ?? $tender->tender_source,
                'date_of_Publication' => $validatedData['date_of_Publication'] ?? $tender->date_of_Publication,
                'expired_at' => $validatedData['expired_at'] ?? $tender->expired_at,
                'bid_submission' => $validatedData['bid_submission'] ?? $tender->bid_submission,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Tender updated successfully',
                'data' => $tender,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error during tender update: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while updating the tender',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function deleteCloudinaryFile($fileUrl)
    {
        $publicId = basename(parse_url($fileUrl, PHP_URL_PATH), '.' . pathinfo($fileUrl, PATHINFO_EXTENSION));
        $this->cloudinary->uploadApi()->destroy('tender_attachments/' . $publicId);
    }

    public function destroy(string $id)
    {
        try {
            $tender = Tender::findOrFail($id);
            if ($tender->attachment) {
                $this->deleteCloudinaryFile($tender->attachment);
            }
            $tender->delete();

            return response()->json([
                'status' => true,
                'message' => 'Tender deleted successfully.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete tender.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function countTenders()
    {
        try {
            $tenderCount = Tender::count();
            return response()->json([
                'status' => true,
                'message' => 'Total tenders count retrieved successfully.',
                'registered_tenders' => $tenderCount
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve tender count.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getTenderReport(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'from' => 'required|date',
                'to' => 'required|date|after_or_equal:from',
                'tender_type' => 'required|string',
            ]);

            $query = Tender::whereBetween(DB::raw("DATE(created_at)"), [$validatedData['from'], $validatedData['to']]);
            if ($validatedData['tender_type'] !== 'all-tenders') {
                $query->where('tender_type', $validatedData['tender_type']);
            }

            $tenders = $query->orderBy('created_at', 'desc')->get();

            if ($tenders->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Tender not found.',
                    'error' => 'No query results for model [App\\Models\\Tender].'
                ], 404);
            }

            $formattedTenders = $tenders->map(function ($tender) {
                return [
                    'tender_id' => $tender->id,
                    'title' => $tender->title,
                    'tender_type' => $tender->tender_type,
                    'tender_source' => $tender->tender_source,
                    'procurement_entity' => $tender->procurement_entity,
                    'tender_number' => $tender->tender_number,
                    'user_id' => $tender->user_id,
                    'attachment' => $tender->attachment,
                    'date_of_Publication' => $tender->date_of_Publication,
                    'expired_at' => $tender->expired_at,
                    'bid_submission' => $tender->bid_submission,
                    'created_at' => $tender->created_at->format('Y-m-d'),
                    'updated_at' => $tender->updated_at->format('Y-m-d'),
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Tenders fetched successfully.',
                'data' => $formattedTenders
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error fetching tenders report: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching the report.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAllTenderTypes()
    {
        try {
            $tenderTypes = Tender::all();

            if ($tenderTypes->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No tender types found.',
                    'error' => 'No query results for tender types.'
                ], 404);
            }

            $formattedTenderTypes = $tenderTypes->map(function ($type) {
                return [
                    'type_id' => $type->id,
                    'tender_type' => $type->tender_type,
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Tender types fetched successfully.',
                'data' => $formattedTenderTypes
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error fetching tender types: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching tender types.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function countHodTenders()
    {
        try {
            $count = AssignTender::count();

            return response()->json([
                'status' => true,
                'count' => $count,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error counting HOD assigned tenders: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to count HOD assigned tenders.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function hodTenders(Request $request)
    {
        try {
            $data = AssignTender::with([
                'tender:tender_id,title,tender_type,procurement_entity,tender_number,attachment,date_of_Publication,bid_submission,expired_at',
                'user:user_id,name'
            ])
                ->orderBy('assign_id', 'desc')
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Assigned tenders fetched successfully.',
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching HOD assigned tenders: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch assigned tenders.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
