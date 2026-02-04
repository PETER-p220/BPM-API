<?php

namespace App\Jobs;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class SendProjectNotification extends Job
{
    protected $project;

    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    public function handle()
    {
        try {
            // Notify Project Owner
            $user = User::find($this->project->user_id);
            $subject = 'Project Due in 7 Days: ' . $this->project->project_name;

            Mail::raw("Your project '{$this->project->project_name}' is due in 7 days.", function ($message) use ($user, $subject) {
                $message->to($user->email)
                        ->subject($subject);
            });

            // Notify HOD
            $hod = User::find($this->project->is_sent_to_hod);
            Mail::raw("Project '{$this->project->project_name}' assigned to your department is due in 7 days.", function ($message) use ($hod, $subject) {
                $message->to($hod->email)
                        ->subject($subject);
            });

            // Mark project as notified
            $this->project->notification_sent = true;
            $this->project->save();

        } catch (\Exception $e) {
            \Log::error('Failed to send email for project ' . $this->project->id . ': ' . $e->getMessage());
        }
    }
}
