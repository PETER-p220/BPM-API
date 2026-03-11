<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomatedTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'schedule',
        'enabled',
        'last_run',
        'next_run',
        'last_result',
        'parameters'
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'parameters' => 'array',
        'last_run' => 'datetime',
        'next_run' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $dates = [
        'last_run',
        'next_run',
        'created_at',
        'updated_at'
    ];

    /**
     * Scope to get only enabled tasks.
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope to get only disabled tasks.
     */
    public function scopeDisabled($query)
    {
        return $query->where('enabled', false);
    }

    /**
     * Scope to get tasks that need to run.
     */
    public function scopeNeedsRun($query)
    {
        return $query->where('next_run', '<=', now())
                    ->where('enabled', true);
    }

    /**
     * Check if task is enabled.
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * Check if task is disabled.
     */
    public function isDisabled()
    {
        return !$this->enabled;
    }

    /**
     * Check if task needs to run.
     */
    public function needsRun()
    {
        return $this->enabled && $this->next_run && $this->next_run->isPast();
    }

    /**
     * Check if task last run was successful.
     */
    public function lastRunSuccessful()
    {
        return $this->last_result === 'success';
    }

    /**
     * Mark task as completed.
     */
    public function markAsCompleted($result = 'success')
    {
        $this->update([
            'last_run' => now(),
            'last_result' => $result,
            'next_run' => $this->calculateNextRun()
        ]);
    }

    /**
     * Calculate next run time based on schedule.
     */
    private function calculateNextRun()
    {
        switch ($this->schedule) {
            case 'daily':
                return now()->addDay();
            case 'weekly':
                return now()->addWeek();
            case 'monthly':
                return now()->addMonth();
            case 'hourly':
                return now()->addHour();
            default:
                return now()->addDay(); // Default to daily
        }
    }
}
