<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class VTSController extends Controller
{
    public function dashboard()
    {
        $url = config('vts.url');
        $key = config('vts.key');

        // Log configuration for debugging
        \Log::info('VTS Config:', [
            'url' => $url,
            'key' => $key ? 'SET' : 'MISSING'
        ]);

        if (!$url || !$key) {
            return response()->json([
                'success' => false,
                'message' => 'VTS API configuration missing',
                'debug' => [
                    'url' => $url,
                    'key_set' => !empty($key)
                ]
            ], 500);
        }

        try {
            $startTime = microtime(true);

            // Add caching to reduce API calls - cache for 4 minutes
            $cacheKey = 'vts_dashboard_data';
            $cachedData = cache()->get($cacheKey);
            
            if ($cachedData) {
                \Log::info('VTS Data served from cache');
                return response()->json([
                    'success'       => true,
                    'data'          => $cachedData,
                    'response_time' => 0, // From cache
                    'last_updated'  => cache()->get($cacheKey . '_updated', now()->toDateTimeString()),
                    'cached'        => true,
                ]);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $key,
                'Accept'        => 'application/json',
            ])->timeout(25) // 25 second timeout for external API
            ->get($url);

            $responseTime = round((microtime(true) - $startTime) * 1000); // in ms

            if ($response->successful()) {
                $data = $response->json();
                
                // Cache the successful response for 4 minutes (240 seconds)
                cache()->put($cacheKey, $data, 240);
                cache()->put($cacheKey . '_updated', now()->toDateTimeString(), 240);
                
                // Log the response for debugging (only log response time, not full data for performance)
                \Log::info('VTS API Response:', [
                    'status' => $response->status(),
                    'response_time' => $responseTime,
                    'cached' => false
                ]);

                return response()->json([
                    'success'       => true,
                    'data'          => $data,
                    'response_time' => $responseTime,
                    'last_updated'  => now()->toDateTimeString(),
                    'cached'        => false,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'VTS API error: ' . $response->status(),
                    'error'   => $response->body()
                ], $response->status());
            }
        } catch (\Exception $e) {
            // Try to serve stale cache if available
            $cacheKey = 'vts_dashboard_data';
            $staleData = cache()->get($cacheKey);
            
            if ($staleData && str_contains($e->getMessage(), 'timeout')) {
                \Log::warning('VTS API timeout, serving stale cache');
                return response()->json([
                    'success'       => true,
                    'data'          => $staleData,
                    'response_time' => 0,
                    'last_updated'  => cache()->get($cacheKey . '_updated', now()->toDateTimeString()),
                    'cached'        => true,
                    'stale'         => true,
                    'warning'       => 'Showing cached data due to API timeout',
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch VTS data',
                'error'   => $e->getMessage()
            ], 502);
        }
    }
}
