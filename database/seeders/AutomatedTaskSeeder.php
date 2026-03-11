<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AutomatedTask;

class AutomatedTaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $tasks = [
            [
                'name' => 'Data Backup',
                'description' => 'Automatic daily backup of financial data',
                'schedule' => 'daily',
                'enabled' => true,
                'next_run' => now()->addDay(),
                'parameters' => [
                    'backup_path' => storage_path('app/backups'),
                    'include_tables' => ['financial_records', 'maintenance_logs']
                ]
            ],
            [
                'name' => 'Data Validation',
                'description' => 'Validate data integrity and consistency',
                'schedule' => 'weekly',
                'enabled' => true,
                'next_run' => now()->addWeek(),
                'parameters' => [
                    'check_null_amounts' => true,
                    'check_future_dates' => true,
                    'check_invalid_categories' => true
                ]
            ],
            [
                'name' => 'Log Cleanup',
                'description' => 'Clean up old system logs',
                'schedule' => 'monthly',
                'enabled' => false,
                'next_run' => now()->addMonth(),
                'parameters' => [
                    'retention_days' => 30
                ]
            ]
        ];

        foreach ($tasks as $task) {
            AutomatedTask::create($task);
        }
    }
}
