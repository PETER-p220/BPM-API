<?php

namespace App\Http\Controllers;

use App\Models\TenderReport;
use App\Models\Tender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class TenderReportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $reports = TenderReport::with(['tender:tender_id,title', 'user:user_id,name'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($report) {
                return [
                    'id' => $report->id,
                    'tender_title' => $report->tender ? $report->tender->title : 'Unknown Tender',
                    'company_name' => $report->user ? $report->user->name : 'Unknown',
                    'report_type' => $report->report_type,
                    'reason' => $report->reason,
                    'recommendations' => $report->recommendations,
                    'reported_by' => $report->reported_by,
                    'created_at' => $report->created_at,
                    'supporting_document' => !empty($report->supporting_document)
                ];
            });

        return response()->json($reports);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'tender_id' => 'required|exists:tenders,tender_id',
            'report_type' => 'required|in:technical,financial,compliance,documentation,other',
            'reason' => 'required|string|min:10',
            'recommendations' => 'nullable|string',
            'supporting_document' => 'nullable|file|mimes:pdf|max:10240', // 10MB max
        ]);

        $report = new TenderReport();
        $report->tender_id = $request->tender_id;
        $report->report_type = $request->report_type;
        $report->reason = $request->reason;
        $report->recommendations = $request->recommendations;
        $report->reported_by = Auth::user()->name;
        $report->user_id = Auth::id();

        // Handle file upload
        if ($request->hasFile('supporting_document')) {
            $file = $request->file('supporting_document');
            $filename = 'tender_report_' . time() . '_' . uniqid() . '.pdf';
            $path = $file->storeAs('tender_reports', $filename, 'public');
            $report->supporting_document = $path;
        }

        $report->save();

        return response()->json($report, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $report = TenderReport::with(['tender'])->findOrFail($id);
        return response()->json($report);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $report = TenderReport::findOrFail($id);
        
        $request->validate([
            'report_type' => 'sometimes|required|in:technical,financial,compliance,documentation,other',
            'reason' => 'sometimes|required|string|min:10',
            'recommendations' => 'nullable|string',
        ]);

        $report->update($request->only(['report_type', 'reason', 'recommendations']));

        return response()->json($report);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $report = TenderReport::findOrFail($id);
        
        // Delete file if exists
        if ($report->supporting_document) {
            Storage::disk('public')->delete($report->supporting_document);
        }
        
        $report->delete();

        return response()->json(null, 204);
    }

    /**
     * Download supporting document
     */
    public function downloadDocument(string $id)
    {
        $report = TenderReport::findOrFail($id);

        if (!$report->supporting_document) {
            return response()->json(['message' => 'No document found'], 404);
        }

        $path = storage_path('app/public/' . $report->supporting_document);
        
        if (!file_exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return response()->download($path, 'tender_report_' . $report->id . '.pdf');
    }
}
