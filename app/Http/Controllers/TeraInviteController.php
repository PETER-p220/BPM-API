<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TeraInviteController extends Controller
{
    public function overview()
    {
        $url = config('tera-invite.url');
        $key = config('tera-invite.key');

        if (!$url || !$key) {
            return response()->json([
                'success' => false,
                'message' => 'API configuration missing'
            ], 500);
        }

        try {
            $startTime = microtime(true);

            $response = Http::withHeaders([
                'X-BPM-API-Key' => $key,
                'Accept'        => 'application/json',
            ])->get($url);

            $responseTime = round((microtime(true) - $startTime) * 1000); // in ms

            if ($response->successful()) {
                $data = $response->json();

                // You can transform the data here if needed
                return response()->json([
                    'success'       => true,
                    'data'          => $data['data'] ?? $data,
                    'response_time' => $responseTime,
                    'last_updated'  => now()->toDateTimeString(),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'External API error: ' . $response->status(),
                    'error'   => $response->body()
                ], $response->status());
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch invitation data',
                'error'   => $e->getMessage()
            ], 502);
        }
    }
}