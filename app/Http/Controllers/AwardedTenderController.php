<?php

namespace App\Http\Controllers;

use App\Models\AwardedTender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AwardedTenderController extends Controller
{
    public function index(Request $request)
    {
        try {
            $awards = AwardedTender::with([
                'user:user_id,name',
                'tender:tender_id,title'
            ])
                ->orderBy('award_id', 'desc')
                ->get();

            $data = $awards->map(function ($award) {
                return [
                    'award_id' => $award->award_id,
                    'user_name' => $award->user?->name,
                    'awarded_document' => $award->awarded_document,
                    'is_sent' => $award->is_sent,
                    'created_at' => optional($award->created_at)->toDateTimeString(),
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Awarded tenders fetched successfully.',
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching awarded tenders: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch awarded tenders.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function countAwardedTenders()
    {
        try {
            $count = AwardedTender::count();

            return response()->json([
                'status' => true,
                'count_awarded_tenders' => $count,
                'count' => $count,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error counting awarded tenders: ' . $e->getMessage());

            return response()->json([         
                'status' => false,
                'message' => 'Failed to count awarded tenders.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
