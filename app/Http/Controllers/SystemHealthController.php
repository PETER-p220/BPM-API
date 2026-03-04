<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SystemHealthController extends Controller
{
    /**
     * Get comprehensive system health status
     */
    public function getSystemHealth()
    {
        try {
            $health = [
                'api' => $this->checkApiStatus(),
                'database' => $this->checkDatabaseStatus(),
                'storage' => $this->checkStorageStatus(),
                'backup' => $this->getLastBackupTime(),
                'memory' => $this->getMemoryUsage(),
                'uptime' => $this->getSystemUptime()
            ];

            $overallStatus = $this->calculateOverallStatus($health);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'overall' => $overallStatus,
                    'components' => $health,
                    'lastChecked' => now()->toISOString()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get system health: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check API status
     */
    private function checkApiStatus()
    {
        return [
            'status' => 'operational',
            'response_time' => $this->getResponseTime(),
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Check database connection
     */
    private function checkDatabaseStatus()
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'healthy',
                'response_time' => $responseTime . 'ms',
                'connection' => 'connected',
                'timestamp' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => 'Connection failed',
                'timestamp' => now()->toISOString()
            ];
        }
    }

    /**
     * Check storage usage
     */
    private function checkStorageStatus()
    {
        try {
            $totalSpace = disk_total_space('/');
            $freeSpace = disk_free_space('/');
            $usedSpace = $totalSpace - $freeSpace;
            $usagePercentage = round(($usedSpace / $totalSpace) * 100, 1);

            return [
                'status' => $usagePercentage > 90 ? 'critical' : ($usagePercentage > 75 ? 'warning' : 'healthy'),
                'usage_percentage' => $usagePercentage,
                'used_space' => $this->formatBytes($usedSpace),
                'free_space' => $this->formatBytes($freeSpace),
                'total_space' => $this->formatBytes($totalSpace),
                'timestamp' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => 'Unable to check storage',
                'timestamp' => now()->toISOString()
            ];
        }
    }

    /**
     * Get last backup time (mock - replace with actual backup logic)
     */
    private function getLastBackupTime()
    {
        // This would typically check your backup system
        // For now, return a realistic timestamp
        $lastBackup = now()->subHours(2);
        
        return [
            'status' => 'completed',
            'last_backup' => $lastBackup->toISOString(),
            'time_ago' => $lastBackup->diffForHumans(),
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Get memory usage
     */
    private function getMemoryUsage()
    {
        if (function_exists('memory_get_usage')) {
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
            
            if ($memoryLimit > 0) {
                $usagePercentage = round(($memoryUsage / $memoryLimit) * 100, 1);
            } else {
                $usagePercentage = 0;
            }

            return [
                'status' => $usagePercentage > 90 ? 'critical' : ($usagePercentage > 75 ? 'warning' : 'healthy'),
                'usage_percentage' => $usagePercentage,
                'used' => $this->formatBytes($memoryUsage),
                'limit' => $this->formatBytes($memoryLimit),
                'timestamp' => now()->toISOString()
            ];
        }

        return [
            'status' => 'unknown',
            'message' => 'Memory usage not available',
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Get system uptime
     */
    private function getSystemUptime()
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return [
                'status' => $load[0] > 2.0 ? 'warning' : 'healthy',
                'load_average' => [
                    '1_min' => round($load[0], 2),
                    '5_min' => round($load[1] ?? 0, 2),
                    '15_min' => round($load[2] ?? 0, 2)
                ],
                'timestamp' => now()->toISOString()
            ];
        }

        return [
            'status' => 'unknown',
            'message' => 'System load not available',
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Calculate overall system status
     */
    private function calculateOverallStatus($health)
    {
        $statuses = [];
        
        foreach ($health as $component) {
            $statuses[] = $component['status'];
        }

        if (in_array('error', $statuses) || in_array('critical', $statuses)) {
            return 'critical';
        } elseif (in_array('warning', $statuses)) {
            return 'warning';
        } elseif (in_array('unknown', $statuses)) {
            return 'degraded';
        } else {
            return 'healthy';
        }
    }

    /**
     * Get API response time
     */
    private function getResponseTime()
    {
        $start = microtime(true);
        // Simple check - in production, you might check a specific endpoint
        usleep(1000); // Simulate minimal processing time
        $responseTime = round((microtime(true) - $start) * 1000, 2);
        
        return $responseTime . 'ms';
    }

    /**
     * Parse memory limit string
     */
    private function parseMemoryLimit($memoryLimit)
    {
        $memoryLimit = strtolower($memoryLimit);
        $multiplier = 1;

        if (strpos($memoryLimit, 'g') !== false) {
            $multiplier = 1024 * 1024 * 1024;
        } elseif (strpos($memoryLimit, 'm') !== false) {
            $multiplier = 1024 * 1024;
        } elseif (strpos($memoryLimit, 'k') !== false) {
            $multiplier = 1024;
        }

        return (int) $memoryLimit * $multiplier;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
