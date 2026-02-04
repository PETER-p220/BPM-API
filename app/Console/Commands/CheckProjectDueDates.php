<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\ProjectController;

class CheckProjectDueDates extends Command
{
    protected $signature = 'project:check-due-dates';
    protected $description = 'Check and send reminders for project due dates';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        // Call your controller method to check due dates
        app(ProjectController::class)->checkProjectDueDates();

        // Provide feedback that the task ran successfully
        $this->info('Project due dates checked and reminders sent successfully.');
    }
}
