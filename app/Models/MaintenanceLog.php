<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'task',
        'level',
        'message',
        'user_id',
        'ip_address',
        'details'
    ];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $dates = [
        'created_at',
        'updated_at'
    ];

    /**
     * Get the user who performed the maintenance.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Scope to get logs by level.
     */
    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope to get logs for a specific task.
     */
    public function scopeByTask($query, $task)
    {
        return $query->where('task', $task);
    }

    /**
     * Scope to get logs within date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Check if log is an error.
     */
    public function isError()
    {
        return $this->level === 'error';
    }

    /**
     * Check if log is a warning.
     */
    public function isWarning()
    {
        return $this->level === 'warning';
    }

    /**
     * Check if log is info.
     */
    public function isInfo()
    {
        return $this->level === 'info';
    }

    /**
     * Check if log is debug.
     */
    public function isDebug()
    {
        return $this->level === 'debug';
    }
}
