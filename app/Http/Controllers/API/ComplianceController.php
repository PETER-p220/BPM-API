<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ComplianceSubmission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ComplianceController extends Controller
{
    /**
     * Display a listing of compliance submissions.
     */
    public function index(Request $request)
    {
        $query = ComplianceSubmission::with(['user', 'attachments']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%");
            });
        }
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        $submissions = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json([
            'data' => $submissions->items(),
            'pagination' => [
                'current_page' => $submissions->currentPage(),
                'last_page' => $submissions->lastPage(),
                'per_page' => $submissions->perPage(),
                'total' => $submissions->total(),
            ]
        ]);
    }

    /**
     * Store a newly created compliance submission.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'required|in:financial,procurement,ethical,safety,other,operational,legal,environmental,hr',
            'priority' => 'required|in:low,medium,high,critical',
            'attachments.*' => 'nullable|file|max:10240', // 10MB max per file
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['user_id'] = Auth::id();
        $data['reference_number'] = 'COMP-' . date('Y') . '-' . str_pad(ComplianceSubmission::count() + 1, 6, '0', STR_PAD_LEFT);
        $data['status'] = 'pending';

        $submission = ComplianceSubmission::create($data);

        // Handle file attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('compliance-attachments', 'public');
                $submission->attachments()->create([
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_size' => $file->getSize(),
                    'file_type' => $file->getMimeType(),
                ]);
            }
        }

        return response()->json($submission->load(['user', 'attachments']), 201);
    }

    /**
     * Display the specified compliance submission.
     */
    public function show($id)
    {
        $submission = ComplianceSubmission::with(['user', 'attachments'])->find($id);

        if (!$submission) {
            return response()->json(['message' => 'Submission not found'], 404);
        }

        return response()->json($submission);
    }

    /**
     * Update the specified compliance submission.
     */
    public function update(Request $request, $id)
    {
        $submission = ComplianceSubmission::find($id);

        if (!$submission) {
            return response()->json(['message' => 'Submission not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'category' => 'sometimes|in:financial,procurement,ethical,safety,other,operational,legal,environmental,hr',
            'priority' => 'sometimes|in:low,medium,high,critical',
            'status' => 'sometimes|in:pending,under_review,reviewed,resolved,dismissed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $submission->update($request->all());

        return response()->json($submission->load(['user', 'attachments']));
    }

    /**
     * Remove the specified compliance submission.
     */
    public function destroy($id)
    {
        $submission = ComplianceSubmission::find($id);

        if (!$submission) {
            return response()->json(['message' => 'Submission not found'], 404);
        }

        // Delete attachments
        foreach ($submission->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->file_path);
            $attachment->delete();
        }

        $submission->delete();

        return response()->json(['message' => 'Submission deleted successfully']);
    }

    /**
     * Review a compliance submission.
     */
    public function review(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:reviewed,resolved,dismissed',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $submission = ComplianceSubmission::find($id);

        if (!$submission) {
            return response()->json(['message' => 'Submission not found'], 404);
        }

        $submission->update([
            'status' => $request->status,
            'review_notes' => $request->notes,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return response()->json($submission->load(['user', 'attachments', 'reviewer']));
    }

    /**
     * Get compliance statistics.
     */
    public function statistics()
    {
        $stats = [
            'total_submissions' => ComplianceSubmission::count(),
            'pending_review' => ComplianceSubmission::whereIn('status', ['pending', 'under_review'])->count(),
            'resolved' => ComplianceSubmission::where('status', 'resolved')->count(),
            'critical_cases' => ComplianceSubmission::where('priority', 'critical')->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Export compliance submissions to Excel.
     */
    public function export(Request $request)
    {
        $query = ComplianceSubmission::with(['user', 'attachments']);

        // Apply same filters as index method
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%");
            });
        }
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        $submissions = $query->orderBy('created_at', 'desc')->get();

        // Create Excel export (simplified version)
        $filename = 'compliance_submissions_' . date('Y-m-d') . '.csv';
        $handle = fopen('php://memory', 'r+');
        
        // CSV header
        fputcsv($handle, [
            'Reference Number',
            'Title',
            'Category',
            'Priority',
            'Status',
            'Submitted By',
            'Submitted Date',
            'Review Notes'
        ]);

        // CSV data
        foreach ($submissions as $submission) {
            fputcsv($handle, [
                $submission->reference_number,
                $submission->title,
                $submission->category,
                $submission->priority,
                $submission->status,
                $submission->user->name ?? 'N/A',
                $submission->created_at->format('Y-m-d H:i:s'),
                $submission->review_notes ?? ''
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Download attachment.
     */
    public function downloadAttachment($submissionId, $attachmentId)
    {
        $submission = ComplianceSubmission::find($submissionId);
        
        if (!$submission) {
            return response()->json(['message' => 'Submission not found'], 404);
        }

        $attachment = $submission->attachments()->find($attachmentId);
        
        if (!$attachment) {
            return response()->json(['message' => 'Attachment not found'], 404);
        }

        $filePath = storage_path('app/public/' . $attachment->file_path);
        
        if (!file_exists($filePath)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return response()->download($filePath, $attachment->file_name);
    }
}
