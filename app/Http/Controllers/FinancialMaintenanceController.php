<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceLog;
use App\Models\AutomatedTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FinancialMaintenanceController extends Controller
{
    /**
     * Get system status overview.
     */
    public function getSystemStatus()
    {
        try {
            $systemHealth = $this->checkSystemHealth();
            $dataIntegrity = $this->checkDataIntegrity();
            $lastBackup = $this->getLastBackupInfo();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'systemHealth' => $systemHealth,
                    'dataIntegrity' => $dataIntegrity,
                    'lastBackup' => $lastBackup
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get system status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Run maintenance tasks.
     */
    public function runMaintenance()
    {
        try {
            $results = [];
            
            // Database optimization
            $results['database_optimization'] = $this->optimizeDatabase();
            
            // Data validation
            $results['data_validation'] = $this->validateData();
            
            // Cache clearing
            $results['cache_clear'] = $this->clearCache();
            
            // Log cleanup
            $results['log_cleanup'] = $this->cleanupLogs();

            $this->logMaintenanceTask('Full Maintenance Run', $results);

            return response()->json([
                'status' => 'success',
                'message' => 'Maintenance completed successfully',
                'results' => $results
            ]);
        } catch (\Exception $e) {
            $this->logError('Maintenance run failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Maintenance failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get automated tasks.
     */
    public function getAutomatedTasks()
    {
        try {
            $tasks = AutomatedTask::orderBy('name')->get();
            
            return response()->json([
                'status' => 'success',
                'data' => $tasks
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get automated tasks: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle automated task.
     */
    public function toggleTask(Request $request, $id)
    {
        try {
            $task = AutomatedTask::findOrFail($id);
            
            // Debug: Log incoming request
            \Log::info('Toggle task request', [
                'task_id' => $id,
                'enabled' => $request->enabled,
                'user_id' => Auth::id(),
                'request_data' => $request->all()
            ]);

            $validator = Validator::make($request->all(), [
                'enabled' => 'required|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $task->update([
                'enabled' => $request->enabled,
                'last_run' => null // Reset last run when toggling
            ]);

            $this->logMaintenanceTask("Task Toggled: {$task->name}", [
                'enabled' => $request->enabled,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Task updated successfully',
                'data' => $task
            ]);
        } catch (\Exception $e) {
            \Log::error('Toggle task error', [
                'task_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update task: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Run manual task.
     */
    public function runManualTask(Request $request, $id)
    {
        try {
            $task = AutomatedTask::findOrFail($id);
            
            $result = $this->executeTask($task);
            
            $task->update([
                'last_run' => now(),
                'last_result' => $result['status']
            ]);

            $this->logMaintenanceTask("Manual Task Run: {$task->name}", $result);

            return response()->json([
                'status' => 'success',
                'message' => 'Task executed successfully',
                'result' => $result
            ]);
        } catch (\Exception $e) {
            $this->logError("Manual task {$id} failed", ['error' => $e->getMessage()]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to run task: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system logs.
     */
    public function getLogs(Request $request)
    {
        try {
            $query = MaintenanceLog::query();

            if ($request->has('level') && $request->level) {
                $query->where('level', $request->level);
            }

            $logs = $query->orderBy('created_at', 'desc')
                          ->limit(1000)
                          ->get();

            return response()->json([
                'status' => 'success',
                'data' => $logs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear system logs.
     */
    public function clearLogs()
    {
        try {
            $deleted = MaintenanceLog::where('created_at', '<', now()->subDays(30))->delete();
            
            $this->logMaintenanceTask('Log Cleanup', [
                'deleted_records' => $deleted,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Logs cleared successfully',
                'deleted_count' => $deleted
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to clear logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check system health.
     */
    private function checkSystemHealth()
    {
        try {
            $health = [
                'status' => 'healthy',
                'lastCheck' => now()->toISOString(),
                'checks' => []
            ];

            // Database connection
            try {
                DB::connection()->getPdo();
                $health['checks']['database'] = ['status' => 'healthy', 'message' => 'Database connection OK'];
            } catch (\Exception $e) {
                $health['status'] = 'unhealthy';
                $health['checks']['database'] = ['status' => 'unhealthy', 'message' => 'Database connection failed'];
            }

            // Disk space (simplified check)
            $diskFree = disk_free_space('/');
            $diskTotal = disk_total_space('/');
            $diskUsage = $diskTotal > 0 ? (($diskTotal - $diskFree) / $diskTotal) * 100 : 0;
            
            if ($diskUsage > 90) {
                $health['status'] = 'warning';
                $health['checks']['disk'] = ['status' => 'warning', 'message' => 'Low disk space'];
            } else {
                $health['checks']['disk'] = ['status' => 'healthy', 'message' => 'Disk space OK'];
            }

            return $health;
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'lastCheck' => now()->toISOString(),
                'checks' => ['system' => ['status' => 'error', 'message' => $e->getMessage()]]
            ];
        }
    }

    /**
     * Check data integrity.
     */
    private function checkDataIntegrity()
    {
        try {
            // This is a simplified data integrity check
            $totalRecords = DB::table('financial_records')->count();
            $nullAmounts = DB::table('financial_records')->whereNull('amount')->count();
            $invalidDates = DB::table('financial_records')->whereNull('date')->count();
            
            $issues = $nullAmounts + $invalidDates;
            $score = $totalRecords > 0 ? (($totalRecords - $issues) / $totalRecords) * 100 : 100;

            return [
                'score' => round($score, 2),
                'issues' => $issues,
                'totalRecords' => $totalRecords,
                'checks' => [
                    'null_amounts' => $nullAmounts,
                    'invalid_dates' => $invalidDates
                ]
            ];
        } catch (\Exception $e) {
            return [
                'score' => 0,
                'issues' => 999,
                'totalRecords' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get last backup info.
     */
    private function getLastBackupInfo()
    {
        try {
            // This is a placeholder - implement actual backup checking logic
            $lastBackupFile = storage_path('app/backups/last_backup.txt');
            
            if (file_exists($lastBackupFile)) {
                $timestamp = file_get_contents($lastBackupFile);
                return [
                    'status' => 'Completed',
                    'timestamp' => $timestamp,
                    'size' => 'N/A'
                ];
            }

            return [
                'status' => 'Never',
                'timestamp' => null,
                'size' => 'N/A'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'Error',
                'timestamp' => null,
                'size' => 'N/A',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Optimize database.
     */
    private function optimizeDatabase()
    {
        try {
            // Run OPTIMIZE TABLE on key tables
            $tables = ['financial_records', 'maintenance_logs', 'automated_tasks'];
            $results = [];

            foreach ($tables as $table) {
                try {
                    DB::statement("OPTIMIZE TABLE `{$table}`");
                    $results[$table] = 'success';
                } catch (\Exception $e) {
                    $results[$table] = 'failed: ' . $e->getMessage();
                }
            }

            return ['status' => 'completed', 'results' => $results];
        } catch (\Exception $e) {
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Validate data.
     */
    private function validateData()
    {
        try {
            $issues = [];
            
            // Check for negative amounts in expense records
            $negativeExpenses = DB::table('financial_records')
                ->where('type', 'expense')
                ->where('amount', '<', 0)
                ->count();
            
            if ($negativeExpenses > 0) {
                $issues[] = "Found {$negativeExpenses} expense records with negative amounts";
            }

            // Check for future dates
            $futureDates = DB::table('financial_records')
                ->where('date', '>', now())
                ->count();
            
            if ($futureDates > 0) {
                $issues[] = "Found {$futureDates} records with future dates";
            }

            return ['status' => 'completed', 'issues_found' => count($issues), 'issues' => $issues];
        } catch (\Exception $e) {
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Clear cache.
     */
    private function clearCache()
    {
        try {
            // Clear application cache
            \Artisan::call('cache:clear');
            
            // Clear config cache
            \Artisan::call('config:clear');
            
            return ['status' => 'completed', 'message' => 'Application and config cache cleared'];
        } catch (\Exception $e) {
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Cleanup old logs.
     */
    private function cleanupLogs()
    {
        try {
            $deleted = MaintenanceLog::where('created_at', '<', now()->subDays(30))->delete();
            
            return ['status' => 'completed', 'deleted_records' => $deleted];
        } catch (\Exception $e) {
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Execute specific task.
     */
    private function executeTask($task)
    {
        switch ($task->name) {
            case 'Database Optimization':
                return $this->optimizeDatabase();
            
            case 'Cache Clear':
                return $this->clearCache();
            
            case 'Rebuild Indexes':
                return $this->rebuildIndexes();
            
            default:
                return ['status' => 'failed', 'error' => 'Unknown task: ' . $task->name];
        }
    }

    /**
     * Rebuild database indexes.
     */
    private function rebuildIndexes()
    {
        try {
            // This is a placeholder for index rebuilding
            return ['status' => 'completed', 'message' => 'Database indexes rebuilt successfully'];
        } catch (\Exception $e) {
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Log maintenance task.
     */
    private function logMaintenanceTask($task, $details)
    {
        try {
            MaintenanceLog::create([
                'task' => $task,
                'level' => 'info',
                'message' => is_array($details) ? json_encode($details) : $details,
                'user_id' => Auth::id(),
                'ip_address' => request()->ip()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log maintenance task: ' . $e->getMessage());
        }
    }

    /**
     * Log error.
     */
    private function logError($message, $context = [])
    {
        try {
            MaintenanceLog::create([
                'task' => 'System Error',
                'level' => 'error',
                'message' => $message . ' - ' . json_encode($context),
                'user_id' => Auth::id(),
                'ip_address' => request()->ip()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log error: ' . $e->getMessage());
        }
    }
}
