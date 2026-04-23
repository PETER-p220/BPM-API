<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class InventoryController extends Controller
{
    public function overview()
    {
        $url = config('inventory.url');
        $key = config('inventory.key');

        // Log configuration for debugging
        \Log::info('Inventory Config:', [
            'url' => $url,
            'key' => $key ? 'SET' : 'MISSING'
        ]);

        if (!$url || !$key) {
            return response()->json([
                'success' => false,
                'message' => 'API configuration missing',
                'debug' => [
                    'url' => $url,
                    'key_set' => !empty($key)
                ]
            ], 500);
        }

        try {
            $startTime = microtime(true);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $key,
                'Accept'        => 'application/json',
            ])->get($url . '/reports/full');

            $responseTime = round((microtime(true) - $startTime) * 1000); // in ms

            if ($response->successful()) {
                $data = $response->json();
                
                // Log the response for debugging
                \Log::info('Inventory API Response:', [
                    'status' => $response->status(),
                    'data' => $data,
                    'response_time' => $responseTime
                ]);

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
                'message' => 'Failed to fetch inventory data',
                'error'   => $e->getMessage()
            ], 502);
        }
    }
}
