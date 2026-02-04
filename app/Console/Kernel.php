<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Http\Controllers\ProjectController;  // Include the controller

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Existing project due dates check - runs every minute
        $schedule->command('project:check-due-dates')->everyMinute();

        // New tender expiration check - runs daily at midnight
        $schedule->call(function () {
            app(\App\Http\Controllers\AssignTenderController::class)->checkExpiringTenders();
        })->daily();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands(): void
    {
        // Load custom Artisan commands
        $this->load(__DIR__.'/Commands');

        // Require console routes
        require base_path('routes/console.php');
    }
}