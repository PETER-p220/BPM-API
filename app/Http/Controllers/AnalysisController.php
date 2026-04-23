<?php

namespace App\Http\Controllers;

use App\Imports\AnalysisImport;
use App\Models\Analysis;
use App\Models\AssignTender;
use App\Models\User; 
use App\Models\Project; 
use App\Models\Tender;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use DateTime;
use DateTimeZone;

class AnalysisController extends Controller
{
    public function index()
    {
        try {
            $analyses = Analysis::with(['project', 'tender', 'user'])->get();
            
            return response()->json([
                'status' => 200,
                'data' => $analyses,
                'message' => 'Analysis data retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching analysis data', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'status' => 500,
                'message' => 'Error retrieving analysis data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function userAnalysis(Request $request)
    {
        try {
            $user = Auth::user();
            $analyses = Analysis::with(['project', 'tender', 'user'])
                ->where('user_id', $user->user_id)
                ->get();
            
            // Group analyses by project or tender and calculate financial totals
            $groupedAnalyses = [];
            foreach ($analyses as $analysis) {
                $projectId = $analysis->project_id;
                $groupKey = $projectId ? 'project-' . $projectId : ($analysis->tender_id ? 'tender-' . $analysis->tender_id : 'analysis-' . $analysis->analysis_id);
                if (!isset($groupedAnalyses[$groupKey])) {
                    $groupedAnalyses[$groupKey] = [
                        'project_id' => $projectId,
                        'tender_id' => $analysis->tender_id,
                        'project' => $analysis->project,
                        'user' => $analysis->user,
                        'tender' => $analysis->tender,
                        'created_at' => $analysis->created_at,
                        'updated_at' => $analysis->updated_at,
                        'status' => $analysis->status ?? 'pending',
                        'reason_for_reject' => $analysis->reason_for_reject ?? null,
                        'file_path' => $analysis->file_path,
                        'items' => [],
                        'total_amount_vat_excl' => 0,
                        'total_amount_vat_incl' => 0,
                        'total_amount_needed' => 0,
                        'site_contingency' => 0,
                        'total_investment' => 0,
                        'projected_profit' => 0,
                        'projected_profit_percentage' => 0
                    ];
                }
                
                // Add item to the project
                $groupedAnalyses[$groupKey]['items'][] = [
                    'analysis_id' => $analysis->analysis_id,
                    'serial_number' => $analysis->serial_number ?? 'N/A',
                    'item_description' => $analysis->item_description ?? 'N/A',
                    'quantity' => $analysis->quantity ?? 0,
                    'amount' => $analysis->amount ?? 0,
                    'rate' => $analysis->rate ?? 0,
                    'quoted_quantity' => $analysis->quoted_quantity ?? $analysis->quantity ?? 0,
                    'quoted_unit' => $analysis->quoted_unit ?? 'pcs',
                    'quoted_rate' => $analysis->quoted_rate ?? $analysis->rate ?? 0,
                    'quoted_amount' => $analysis->quoted_amount ?? ($analysis->quantity * $analysis->rate) ?? 0,
                    'source' => $analysis->source ?? 'N/A',
                    'urgent_status' => $analysis->urgent_status ?? 'normal',
                    'status' => $analysis->status ?? 'pending',
                    'reason_for_reject' => $analysis->reason_for_reject ?? null,
                    'total_amount_vat_excl' => $analysis->total_amount_vat_excl ?? null,
                    'total_amount_vat_incl' => $analysis->total_amount_vat_incl ?? null,
                    'total_amount_needed' => $analysis->total_amount_needed ?? null,
                    'site_contingency' => $analysis->site_contingency ?? null,
                    'total_investment' => $analysis->total_investment ?? null,
                    'projected_profit' => $analysis->projected_profit ?? null,
                    'file_path' => $analysis->file_path ?? null
                ];
                
                // Calculate financial totals for project
                $quotedAmount = floatval($analysis->quoted_amount ?? ($analysis->quantity * $analysis->rate) ?? 0);
                $buyingAmount = floatval($analysis->amount ?? 0);
                $manualVatExcl = floatval($analysis->total_amount_vat_excl ?? 0);
                $manualVatIncl = floatval($analysis->total_amount_vat_incl ?? 0);
                $manualInvestment = floatval($analysis->total_investment ?? 0);
                $manualProfit = floatval($analysis->projected_profit ?? 0);
                $manualProfitPercentage = floatval($analysis->projected_profit_percentage ?? 0);

                if ($analysis->file_path && $manualVatExcl > 0 && $quotedAmount === 0 && $buyingAmount === 0) {
                    $groupedAnalyses[$groupKey]['total_amount_vat_excl'] += $manualVatExcl;
                    $groupedAnalyses[$groupKey]['total_amount_vat_incl'] += $manualVatIncl;
                    $groupedAnalyses[$groupKey]['total_amount_needed'] += floatval($analysis->total_amount_needed ?? 0);
                    $groupedAnalyses[$groupKey]['site_contingency'] += floatval($analysis->site_contingency ?? 0);
                    $groupedAnalyses[$groupKey]['total_investment'] += $manualInvestment;
                    $groupedAnalyses[$groupKey]['projected_profit'] += $manualProfit;
                    if ($manualProfitPercentage > 0) {
                        $groupedAnalyses[$groupKey]['projected_profit_percentage'] = $manualProfitPercentage;
                    }
                    continue;
                }
                
                // VAT calculations (18% VAT rate)
                $vatRate = 0.18;
                $vatAmount = $quotedAmount * $vatRate;
                
                $groupedAnalyses[$groupKey]['total_amount_vat_excl'] += $quotedAmount;
                $groupedAnalyses[$groupKey]['total_amount_vat_incl'] += $quotedAmount + $vatAmount;
                $groupedAnalyses[$groupKey]['total_amount_needed'] += $buyingAmount;
                $groupedAnalyses[$groupKey]['site_contingency'] += $quotedAmount * 0.1; // 10% contingency
                $groupedAnalyses[$groupKey]['total_investment'] += $quotedAmount * 1.2; // 20% investment factor
                $groupedAnalyses[$groupKey]['projected_profit'] += $quotedAmount - $buyingAmount; // Profit margin
            }
            
            // Calculate profit percentage for each project
            foreach ($groupedAnalyses as $projectId => &$project) {
                if ($project['projected_profit_percentage'] > 0) {
                    continue;
                }
                if ($project['total_amount_vat_incl'] > 0) {
                    $project['projected_profit_percentage'] = round(($project['projected_profit'] / $project['total_amount_vat_incl']) * 100, 2);
                }
            }
            
            // Convert to indexed array for frontend compatibility
            $result = array_values($groupedAnalyses);
            
            return response()->json([
                'status' => 200,
                'data' => $result,
                'message' => 'User analysis data retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching user analysis', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            return response()->json([
                'status' => 500,
                'message' => 'Error retrieving user analysis',
                'error' => $e->getMessage()
            ], 500);
        }
    }


public function ItemsDropdown(Request $request)
{
    try {
        $user = Auth::user();
        $query = Analysis::where('user_id', $user->user_id);

        // Filter by project_id if provided
        if ($request->has('project_id') && $request->input('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }

        $analyses = $query->get()->map(function ($analysis) {
            return [
                'analysis_id' => $analysis->analysis_id,
                'items' => [$analysis->item_description ?: 'N/A'], // Handle null item_description
                'item_description' => $analysis->item_description ?: 'N/A',
                'quantity' => $analysis->quantity ?: 0,
                'amount' => $analysis->amount ?: 0,
                'rate' => $analysis->rate ?: 0,
                'serial_number' => $analysis->serial_number ?: 'N/A'
            ];
        });

        return response()->json([
            'status' => 200,
            'data' => $analyses,
            'message' => 'User items dropdown data retrieved successfully'
        ], 200);
    } catch (\Exception $e) {
        Log::error('Error fetching items dropdown', [
            'error' => $e->getMessage(),
            'user_id' => Auth::id()
        ]);
        return response()->json([
            'status' => 500,
            'message' => 'Error retrieving items dropdown',
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function store(Request $request)
    {
        try {
            // Add debug logging before validation
            Log::info('Request data received', [
                'has_file' => $request->hasFile('excel_file'),
                'all_data' => array_keys($request->all())
            ]);

            // Check if user already has a pending analysis for this project/tender
            $existingQuery = Analysis::where('user_id', Auth::id())
                                    ->whereIn('status', ['pending', 'approved']);

            if ($request->project_id) {
                $existingQuery->where('project_id', $request->project_id);
            } elseif ($request->tender_id) {
                $existingQuery->where('tender_id', $request->tender_id);
            }

            $existingAnalysis = $existingQuery->first();

            if ($existingAnalysis) {
                $message = $existingAnalysis->status === 'approved' 
                    ? 'You already have an approved analysis for this project. Please contact admin if you need to make changes.'
                    : 'You already have a pending analysis for this project. Please wait for admin approval before submitting a new one.';
                
                return response()->json([
                    'status' => 422,
                    'message' => $message,
                    'existing_status' => $existingAnalysis->status
                ], 422);
            }

            // Temporarily remove mime validation to see what we get
            $request->validate([
                'excel_file' => 'required|file|max:10240', // Removed mimes validation temporarily
                'project_id' => 'nullable|exists:projects,project_id',
                'tender_id' => 'nullable|exists:tenders,tender_id',
                'serial_number' => 'nullable|string|max:255',
                'item_description' => 'nullable|string|max:255',
                'quoted_quantity' => 'nullable|integer|min:0',
                'quoted_unit' => 'nullable|string|max:50',
                'quoted_rate' => 'nullable|numeric|min:0',
                'quoted_amount' => 'nullable|numeric|min:0',
                'quantity' => 'nullable|integer|min:0',
                'rate' => 'nullable|numeric|min:0',
                'amount' => 'nullable|numeric|min:0',
                'source' => 'nullable|string|max:255',
                'urgent_status' => 'nullable|in:urgent,normal,low',
                'total_amount_vat_excl' => 'nullable|numeric|min:0',
                'total_amount_vat_incl' => 'nullable|numeric|min:0',
                'total_amount_needed' => 'nullable|numeric|min:0',
                'site_contingency' => 'nullable|numeric|min:0',
                'total_investment' => 'nullable|numeric|min:0',
                'projected_profit' => 'nullable|numeric',
                'projected_profit_percentage' => 'nullable|numeric',
            ]);
    
            // Debug: Log file information
            $file = $request->file('excel_file');
            Log::info('File upload details', [
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'extension' => $file->getClientOriginalExtension(),
                'is_valid' => $file->isValid(),
                'error' => $file->getError(),
                'tmp_path' => $file->getPathname()
            ]);

            // Check file validity
            if (!$file->isValid()) {
                Log::error('File validation failed', ['error' => $file->getError()]);
                return response()->json([
                    'status' => 400,
                    'message' => 'File validation failed: ' . $file->getError()
                ], 400);
            }

            $fileExtension = strtolower($file->getClientOriginalExtension());
            $isPdf = in_array($fileExtension, ['pdf']) || $file->getMimeType() === 'application/pdf';
            $isExcel = in_array($fileExtension, ['xlsx', 'xls']) || in_array($file->getMimeType(), [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel',
                'application/octet-stream',
                'text/plain',
                'text/csv',
                'application/csv'
            ]);

            if (!$isPdf && !$isExcel) {
                return response()->json([
                    'status' => 400,
                    'message' => 'File type not allowed. Please upload an Excel (.xlsx, .xls) or PDF file.'
                ], 400);
            }

            $rowCount = 0;

            if ($isPdf) {
                // Store PDF file and create a single analysis record
                $storedPath = $file->store('analyses/documents', 'public');

                $totalVatExcl = $request->input('total_amount_vat_excl');
                $totalVatIncl = $request->input('total_amount_vat_incl');
                $projectedProfit = $request->input('projected_profit');
                $projectedProfitPercentage = $request->input('projected_profit_percentage');

                if ($totalVatExcl !== null && $totalVatIncl === null) {
                    $totalVatIncl = round(((float) $totalVatExcl) * 1.18, 2);
                }

                if ($totalVatExcl !== null && $projectedProfit !== null && $projectedProfitPercentage === null && (float) $totalVatExcl > 0) {
                    $projectedProfitPercentage = round((((float) $projectedProfit) / ((float) $totalVatExcl)) * 100, 2);
                }

                Analysis::create([
                    'project_id' => $request->project_id,
                    'tender_id' => $request->tender_id,
                    'user_id' => Auth::id(),
                    'file_path' => $storedPath,
                    'item_description' => 'PDF Document: ' . $file->getClientOriginalName(),
                    'serial_number' => 'PDF-' . time(),
                    'total_amount_vat_excl' => $totalVatExcl,
                    'total_amount_vat_incl' => $totalVatIncl,
                    'total_amount_needed' => $request->input('total_amount_needed'),
                    'site_contingency' => $request->input('site_contingency'),
                    'total_investment' => $request->input('total_investment'),
                    'projected_profit' => $projectedProfit,
                    'projected_profit_percentage' => $projectedProfitPercentage,
                ]);

                $rowCount = 1;
            } else {
                // Excel import flow
                try {
                    $importer = new AnalysisImport($request->project_id, $request->tender_id);
                    Excel::import($importer, $file);
                } catch (\Exception $excelException) {
                    Log::error('Excel import failed', [
                        'error' => $excelException->getMessage(),
                        'file_path' => $file->getPathname()
                    ]);
                    
                    return response()->json([
                        'status' => 400,
                        'message' => 'Failed to read Excel file. Please ensure it is a valid .xlsx or .xls file and not corrupted.'
                    ], 400);
                }
    
                $rowCountQuery = Analysis::where('user_id', Auth::id())
                                    ->whereNotNull('serial_number');
                if ($request->project_id) {
                    $rowCountQuery->where('project_id', $request->project_id);
                } elseif ($request->tender_id) {
                    $rowCountQuery->where('tender_id', $request->tender_id);
                }
                $rowCount = $rowCountQuery->count();
    
                if ($rowCount === 0) {
                    throw new \Exception('No meaningful data was imported from the Excel file');
                }
                
                // Update the imported analysis with additional fields if provided
                $updateData = [];
                if ($request->tender_id) $updateData['tender_id'] = $request->tender_id;
                if ($request->source) $updateData['source'] = $request->source;
                if ($request->urgent_status) $updateData['urgent_status'] = $request->urgent_status;
                
                if (!empty($updateData)) {
                    $updateQuery = Analysis::where('user_id', Auth::id());
                    if ($request->project_id) {
                        $updateQuery->where('project_id', $request->project_id);
                    } elseif ($request->tender_id) {
                        $updateQuery->where('tender_id', $request->tender_id);
                    }
                    $updateQuery->update($updateData);
                }
            }
    
            // Auto-mark tender as 'quoted' after successful upload
            if ($request->tender_id) {
                AssignTender::where('tender_id', $request->tender_id)
                    ->where('user_id', Auth::id())
                    ->whereIn('is_assigned', ['on-progress', 'rejected'])
                    ->update([
                        'is_assigned' => 'quoted',
                        'ceo_comment' => null,
                    ]);
            }

            Log::info('Analysis data imported successfully', [
                'user_id' => Auth::id(),
                'project_id' => $request->project_id,
                'row_count' => $rowCount
            ]);
    
            // Get uploader details
            $uploader = Auth::user();
            $uploaderName = $uploader->name;
            $uploaderEmail = $uploader->email;
    
            // Get project/tender details for notifications
            $projectName = 'Unknown Project';
            if ($request->project_id) {
                $project = \App\Models\Project::find($request->project_id);
                $projectName = $project->project_name ?? 'Unknown Project';
            } elseif ($request->tender_id) {
                $tender = \App\Models\Tender::find($request->tender_id);
                $projectName = $tender->title ?? 'Unknown Tender';
            }
    
            // Notify users with role_id = 1
            $adminUsers = User::where('role_id', 1)
                              ->where('user_id', '!=', Auth::id()) // Exclude uploader if they're an admin
                              ->get();
    
            $adminSubject = "New Analysis Uploaded for Review: {$projectName}";
            $adminBody = "A new analysis has been uploaded by {$uploaderName} for the project '{$projectName}'.\n"
                       . "Rows imported: {$rowCount}.\n"
                       . "Please log in to the portal to review the details.";
    
            foreach ($adminUsers as $admin) {
                try {
                    Mail::raw($adminBody, function ($message) use ($admin, $adminSubject) {
                        $message->to($admin->email)
                                ->subject($adminSubject);
                    });
                    Log::info('Email sent to admin', ['email' => $admin->email]);
                } catch (\Exception $e) {
                    Log::error('Failed to send email to admin', [
                        'email' => $admin->email,
                        'error' => $e->getMessage()
                    ]);
                }
            }
    
            // Notify the uploader
            $uploaderSubject = "Your Analysis Upload was Successful: {$projectName}";
            $uploaderBody = "Hi {$uploaderName},\n"
                          . "Your analysis for the project '{$projectName}' has been successfully uploaded.\n"
                          . "Rows imported: {$rowCount}.\n"
                          . "The team has been notified for review.";
    
            try {
                Mail::raw($uploaderBody, function ($message) use ($uploaderEmail, $uploaderSubject) {
                    $message->to($uploaderEmail)
                            ->subject($uploaderSubject);
                });
                Log::info('Email sent to uploader', ['email' => $uploaderEmail]);
            } catch (\Exception $e) {
                Log::error('Failed to send email to uploader', [
                    'email' => $uploaderEmail,
                    'error' => $e->getMessage()
                    ]);
            }
    
            return response()->json([
                'status' => 201,
                'message' => 'Analysis data imported successfully',
                'rows_imported' => $rowCount
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error importing analysis data', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
    
            return response()->json([
                'status' => 500,
                'message' => 'Error importing analysis data',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function show($analysis_id)
    {
        try {
            $analysis = Analysis::with(['project', 'tender', 'user'])
                ->findOrFail($analysis_id);

            return response()->json([
                'status' => 200,
                'data' => $analysis,
                'message' => 'Analysis retrieved successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Analysis not found'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error fetching analysis', [
                'analysis_id' => $analysis_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Error retrieving analysis',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function update(Request $request)
    {
        try {
            // Validate the request
            $request->validate([
                'project_id' => 'required|exists:projects,project_id',
                'status' => 'required|in:approved,rejected',
                'reason_for_reject' => 'required_if:status,rejected|nullable|string',
            ]);

            $projectId = $request->input('project_id');
            $status = $request->input('status');
            $reasonForReject = $request->input('reason_for_reject');

            // Check all analyses for the given project_id that are still pending
            $pendingAnalyses = Analysis::where('project_id', $projectId)
                                       ->where('status', 'pending')
                                       ->get();

            if ($pendingAnalyses->isEmpty()) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Analysis for  that project  already approved/rejected cannot  be updated  again please  contact  with administrator'
                ], 404);
            }

            // Update only pending analyses
            $updatedCount = Analysis::where('project_id', $projectId)
                                   ->where('status', 'pending')
                                   ->update([
                                       'status' => $status,
                                       'reason_for_reject' => $status === 'rejected' ? $reasonForReject : null,
                                       'updated_at' => now(),
                                   ]);

            Log::info('Pending analyses updated successfully', [
                'project_id' => $projectId,
                'status' => $status,
                'updated_count' => $updatedCount,
                'user_id' => Auth::id()
            ]);

            // Get project details and uploader (based on pending analyses)
            $project = Project::find($projectId);
            $projectName = $project->project_name ?? 'Unknown Project';
            $uploaderId = $pendingAnalyses->first()->user_id; // Use first pending analysis uploader
            $uploader = User::find($uploaderId);

            if ($uploader) {
                // Format date/time in East Africa Time (EAT, UTC+3)
                $dateTime = new DateTime('now', new DateTimeZone('Africa/Nairobi'));
                $formattedDateTime = $dateTime->format('Y-m-d H:i:s');

                $subject = "Your Analysis has been " . ucfirst($status);
                $body = "Hi {$uploader->name},\n"
                      . "Your analysis for project_name: {$projectName} has been {$status} on {$formattedDateTime} EAT.\n"
                      . "Please log in to the portal for more details.";

                try {
                    Mail::raw($body, function ($message) use ($uploader, $subject) {
                        $message->to($uploader->email)
                                ->subject($subject);
                    });
                    Log::info('Email sent to uploader', ['email' => $uploader->email, 'project_id' => $projectId]);
                } catch (\Exception $e) {
                    Log::error('Failed to send email to uploader', [
                        'email' => $uploader->email,
                        'project_id' => $projectId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'status' => 200,
                'message' => "Updated {$updatedCount} pending analyses successfully",
                'data' => [
                    'project_id' => $projectId,
                    'status' => $status,
                    'reason_for_reject' => $reasonForReject,
                    'updated_count' => $updatedCount
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error updating analyses', [
                'project_id' => $request->input('project_id', 'unknown'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Error updating analyses',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function destroy($analysis_id)
    {
        try {
            $analysis = Analysis::findOrFail($analysis_id);
            $analysis->delete();

            Log::info('Analysis deleted successfully', [
                'analysis_id' => $analysis_id,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Analysis deleted successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Analysis not found'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error deleting analysis', [
                'analysis_id' => $analysis_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Error deleting analysis',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
 * Count distinct project_ids for all user analyses
 */
public function countUserAnalyses()
{
    try {
        $user = Auth::user();
        Log::info('Counting distinct project_ids for user analyses', [
            'user_id' => $user->user_id
        ]);

        $count = Analysis::where('user_id', $user->user_id)
            ->distinct('project_id')
            ->count('project_id');

        return response()->json([
            'status' => 200,
            'total_count' => $count,
            'message' => 'User analyses for project counted successfully'
        ], 200);
    } catch (\Exception $e) {
        Log::error('Error counting user analyses', [
            'error' => $e->getMessage(),
            'user_id' => Auth::id()
        ]);
        return response()->json([
            'status' => 500,
            'message' => 'Error counting user analyses'
        ], 500);
    }
}

/**
 * Count distinct project_ids with approved user analyses
 */
public function countApprovedUserAnalyses()
{
    try {
        $user = Auth::user();
        Log::info('Counting distinct project_ids for approved user analyses', [
            'user_id' => $user->user_id
        ]);

        $count = Analysis::where('user_id', $user->user_id)
            ->where('status', 'approved')
            ->distinct('project_id')
            ->count('project_id');

        return response()->json([
            'status' => 200,
            'approved_count' => $count,
            'message' => 'Approved user analyses for project counted successfully'
        ], 200);
    } catch (\Exception $e) {
        Log::error('Error counting approved user analyses', [
            'error' => $e->getMessage(),
            'user_id' => Auth::id()
        ]);
        return response()->json([
            'status' => 500,
            'message' => 'Error counting approved user analyses'
        ], 500);
    }
}

/**
 * Count distinct project_ids with rejected user analyses
 */
public function countRejectedUserAnalyses()
{
    try {
        $user = Auth::user();
        Log::info('Counting distinct project_ids for rejected user analyses', [
            'user_id' => $user->user_id
        ]);

        $count = Analysis::where('user_id', $user->user_id)
            ->where('status', 'rejected')
            ->distinct('project_id')
            ->count('project_id');

        return response()->json([
            'status' => 200,
            'rejected_count' => $count,
            'message' => 'Rejected user analyses for project counted successfully'
        ], 200);
    } catch (\Exception $e) {
        Log::error('Error counting rejected user analyses', [
            'error' => $e->getMessage(),
            'user_id' => Auth::id()
        ]);
        return response()->json([
            'status' => 500,
            'message' => 'Error counting rejected user analyses'
        ], 500);
    }
}



public function updateFromExcel(Request $request)
{
    try {
        // Validate the request
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls',
            'project_id' => 'required|exists:projects,project_id',
        ]);

        $projectId = $request->input('project_id');
        $file = $request->file('excel_file');

        // Check if user has a pending analysis for this project
        $existingAnalysis = Analysis::where('project_id', $projectId)
                                ->where('user_id', Auth::id())
                                ->where('status', 'pending')
                                ->first();

        if ($existingAnalysis) {
            return response()->json([
                'status' => 422,
                'message' => 'Cannot update analysis while it is pending approval. Please wait for admin approval first.'
            ], 422);
        }

        // Check if there are existing analyses for the project
        $existingAnalyses = Analysis::where('project_id', $projectId)->count();
        
        if ($existingAnalyses === 0) {
            return response()->json([
                'status' => 404,
                'message' => 'No existing analyses found for the provided project_id'
            ], 404);
        }

        // Delete existing analyses for the project
        Analysis::where('project_id', $projectId)->delete();

        // Import new data from Excel
        $importer = new AnalysisImport($projectId);
        Excel::import($importer, $file);

        // Count imported rows
        $rowCount = Analysis::where('project_id', $projectId)
            ->whereNotNull('serial_number')
            ->count();

        if ($rowCount === 0) {
            throw new \Exception('No meaningful data was imported from the Excel file');
        }

        // Log the update
        Log::info('Analysis data updated successfully from Excel', [
            'user_id' => Auth::id(),
            'project_id' => $projectId,
            'row_count' => $rowCount
        ]);

        // Get uploader and project details
        $uploader = Auth::user();
        $project = Project::find($projectId);
        $projectName = $project->project_name ?? 'Unknown Project';

        // Notify admins (role_id = 1)
        $adminUsers = User::where('role_id', 1)
            ->where('user_id', '!=', Auth::id())
            ->get();

        $dateTime = new DateTime('now', new DateTimeZone('Africa/Nairobi'));
        $formattedDateTime = $dateTime->format('Y-m-d H:i:s');

        $adminSubject = "Analysis Updated for Project: {$projectName}";
        $adminBody = "The analysis for project '{$projectName}' has been updated by {$uploader->name}.\n"
            . "Rows imported: {$rowCount}.\n"
            . "Updated on: {$formattedDateTime} EAT.\n"
            . "Please review the updated analysis in the portal.";

        foreach ($adminUsers as $admin) {
            try {
                Mail::raw($adminBody, function ($message) use ($admin, $adminSubject) {
                    $message->to($admin->email)
                        ->subject($adminSubject);
                });
                Log::info('Email sent to admin', ['email' => $admin->email]);
            } catch (\Exception $e) {
                Log::error('Failed to send email to admin', [
                    'email' => $admin->email,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Notify uploader
        $uploaderSubject = "Analysis Update Successful: {$projectName}";
        $uploaderBody = "Hi {$uploader->name},\n"
            . "Your analysis update for project '{$projectName}' has been successfully processed.\n"
            . "Rows imported: {$rowCount}.\n"
            . "Updated on: {$formattedDateTime} EAT.\n"
            . "The team has been notified for review.";

        try {
            Mail::raw($uploaderBody, function ($message) use ($uploader, $uploaderSubject) {
                $message->to($uploader->email)
                    ->subject($uploaderSubject);
            });
            Log::info('Email sent to uploader', ['email' => $uploader->email]);
        } catch (\Exception $e) {
            Log::error('Failed to send email to uploader', [
                'email' => $uploader->email,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Analysis data updated successfully',
            'rows_imported' => $rowCount
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'status' => 422,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);

    } catch (\Exception $e) {
        Log::error('Error updating analysis data from Excel', [
            'error' => $e->getMessage(),
            'user_id' => Auth::id(),
            'project_id' => $request->input('project_id', 'unknown')
        ]);

        return response()->json([
            'status' => 500,
            'message' => 'Error updating analysis data',
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * Approve or reject a project analysis
 */
public function approveAnalysis(Request $request)
{
    try {
        // Validate the request
        $validatedData = $request->validate([
            'project_id' => 'required|exists:projects,project_id',
            'status' => 'required|in:approved,rejected',
            'reason_for_reject' => 'nullable|required_if:status,rejected|string|max:500'
        ]);

        // Find all analyses for the project
        $analyses = Analysis::where('project_id', $validatedData['project_id'])->get();

        if ($analyses->isEmpty()) {
            return response()->json([
                'status' => 404,
                'message' => 'No analyses found for this project'
            ], 404);
        }

        // Update all analyses for the project
        foreach ($analyses as $analysis) {
            $analysis->update([
                'status' => $validatedData['status'],
                'reason_for_reject' => $validatedData['status'] === 'rejected' 
                    ? $validatedData['reason_for_reject'] 
                    : null
            ]);
        }

        // Log the approval/rejection
        Log::info('Project analysis status updated', [
            'user_id' => Auth::id(),
            'project_id' => $validatedData['project_id'],
            'status' => $validatedData['status'],
            'reason_for_reject' => $validatedData['reason_for_reject'] ?? null
        ]);

        return response()->json([
            'status' => 200,
            'message' => "Project analysis {$validatedData['status']} successfully"
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'status' => 422,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);

    } catch (\Exception $e) {
        Log::error('Error approving project analysis', [
            'error' => $e->getMessage(),
            'user_id' => Auth::id(),
            'project_id' => $request->input('project_id', 'unknown')
        ]);

        return response()->json([
            'status' => 500,
            'message' => 'Error updating project analysis status',
            'error' => $e->getMessage()
        ], 500);
    }
}
}