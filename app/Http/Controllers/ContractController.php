<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\Contract;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Cloudinary\Cloudinary;
use Illuminate\Support\Facades\Validator;

class ContractController extends Controller
{
    protected $cloudinary;

    public function __construct()
    {
        try {
            $this->cloudinary = new Cloudinary([
                'cloud' => [
                    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                    'api_key' => env('CLOUDINARY_API_KEY'),
                    'api_secret' => env('CLOUDINARY_API_SECRET'),
                ],
                'url' => [
                    'secure' => true,
                ],
            ]);
        } catch (\Cloudinary\Exception\ConfigurationException $e) {
            Log::error('Cloudinary configuration failed: ' . $e->getMessage());
            $this->cloudinary = null;
        }
    }

    public function index()
    {
        try {
            $contracts = Contract::with('user:user_id,name')
                ->orderBy('contract_id', 'desc')
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Contracts fetched successfully.',
                'data' => $contracts
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching contracts: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch contracts.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function yourContracts()
    {
        try {
            $contracts = Contract::with('user:user_id,name')
                ->where('user_id', Auth::id())
                ->orderBy('contract_id', 'desc')
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Your contracts fetched successfully.',
                'data' => $contracts
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching user contracts: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch your contracts.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'time_line_category' => 'required|string|max:100',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'pdf_file' => 'required|mimes:pdf|max:2048',
                'status' => 'sometimes|in:on-progress,cancelled,ended',
                'performance_guarantee' => 'sometimes|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation errors occurred.',
                    'errors' => $validator->errors()
                ], 400);
            }

            if (!$this->cloudinary) {
                Log::error('Cloudinary is not configured properly.');
                return response()->json([
                    'status' => false,
                    'message' => 'File upload failed due to Cloudinary configuration error.'
                ], 500);
            }

            $pdfUrl = $this->uploadPdfToCloudinary($request);

            if (!$pdfUrl) {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to upload PDF file.'
                ], 500);
            }

            $contract = Contract::create([
                'title' => $request->title,
                'time_line_category' => $request->time_line_category,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'pdf_file' => $pdfUrl,
                'status' => $request->input('status', 'on-progress'),
                'user_id' => Auth::id(),
                'performance_guarantee' => $request->performance_guarantee,
            ]);

            Log::info('Contract created successfully', [
                'contract_id' => $contract->contract_id,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Contract created successfully.',
                'data' => $contract
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating contract: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to create contract.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($contract_id)
    {
        try {
            $contract = Contract::with('user:user_id,name')->find($contract_id);

            if (!$contract) {
                return response()->json([
                    'status' => false,
                    'message' => 'Contract not found.'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Contract retrieved successfully.',
                'data' => $contract
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching contract: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch contract.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $contract_id)
    {
        try {
            $contract = Contract::find($contract_id);

            if (!$contract) {
                return response()->json([
                    'status' => false,
                    'message' => 'Contract not found.'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'time_line_category' => 'sometimes|string|max:100',
                'start_date' => 'sometimes|date',
                'end_date' => 'sometimes|date|after_or_equal:start_date',
                'pdf_file' => 'sometimes|mimes:pdf|max:2048',
                'status' => 'sometimes|in:on-progress,cancelled,ended',
                'performance_guarantee' => 'sometimes|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation errors occurred.',
                    'errors' => $validator->errors()
                ], 400);
            }

            $data = $request->only(['title', 'time_line_category', 'start_date', 'end_date', 'status', 'performance_guarantee']);
            if ($request->hasFile('pdf_file')) {
                if (!$this->cloudinary) {
                    Log::error('Cloudinary is not configured properly.');
                    return response()->json([
                        'status' => false,
                        'message' => 'File upload failed due to Cloudinary configuration error.'
                    ], 500);
                }

                if ($contract->pdf_file) {
                    $publicId = $this->getCloudinaryPublicId($contract->pdf_file);
                    $this->cloudinary->uploadApi()->destroy($publicId);
                }
                $data['pdf_file'] = $this->uploadPdfToCloudinary($request);

                if (!$data['pdf_file']) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Failed to upload PDF file.'
                    ], 500);
                }
            }

            $contract->update($data);

            Log::info('Contract updated successfully', [
                'contract_id' => $contract->contract_id,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Contract updated successfully.',
                'data' => $contract
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating contract: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to update contract.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($contract_id)
    {
        try {
            $contract = Contract::find($contract_id);

            if (!$contract) {
                return response()->json([
                    'status' => false,
                    'message' => 'Contract not found.'
                ], 404);
            }

            if ($contract->pdf_file && $this->cloudinary) {
                $publicId = $this->getCloudinaryPublicId($contract->pdf_file);
                $this->cloudinary->uploadApi()->destroy($publicId);
            }

            $contract->delete();

            Log::info('Contract deleted successfully', [
                'contract_id' => $contract_id,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Contract deleted successfully.'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting contract: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete contract.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getContractTitles()
{
    try {
        $contracts = Contract::select('contract_id', 'title')
            ->orderBy('contract_id', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Contract titles fetched successfully.',
            'data' => $contracts
        ], 200);
    } catch (\Exception $e) {
        Log::error('Error fetching contract titles: ' . $e->getMessage());
        return response()->json([
            'status' => false,
            'message' => 'Failed to fetch contract titles.',
            'error' => $e->getMessage()
        ], 500);
    }
}

    private function uploadPdfToCloudinary(Request $request)
    {
        if ($request->hasFile('pdf_file')) {
            try {
                $file = $request->file('pdf_file');
                $uploadResult = $this->cloudinary->uploadApi()->upload($file->getRealPath(), [
                    'folder' => 'contract_documents',
                    'resource_type' => 'auto',
                ]);

                Log::info('Cloudinary Upload Result for Contract PDF:', (array) $uploadResult);
                return $uploadResult['secure_url'];
            } catch (\Exception $e) {
                Log::error('Cloudinary upload failed: ' . $e->getMessage());
                return null;
            }
        }

        return null;
    }

    private function getCloudinaryPublicId($url)
    {
        $path = parse_url($url, PHP_URL_PATH);
        $parts = explode('/', $path);
        $publicId = end($parts);
        $publicId = str_replace('.' . pathinfo($publicId, PATHINFO_EXTENSION), '', $publicId);
        return 'contract_documents/' . $publicId;
    }
}