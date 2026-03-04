<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Leave extends Model
{
    use HasFactory;

    protected $table = 'leaves';

    protected $primaryKey = 'id';

    protected $fillable = [
        'employee_id',
        'leave_type',
        'start_date',
        'end_date',
        'days',
        'reason',
        'status',
        'requested_by',
        'approver_id',
        'approved_at',
        'rejection_reason',
        'attachments'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
        'attachments' => 'array'
    ];

    protected $dates = [
        'start_date',
        'end_date',
        'approved_at',
        'created_at',
        'updated_at'
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id', 'user_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id', 'user_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by', 'user_id');
    }

    public function scopeFilter($query, $filters)
    {
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }
        
        if (isset($filters['department_id'])) {
            $query->whereHas('employee', function($q) use ($filters) {
                $q->where('department_id', $filters['department_id']);
            });
        }
        
        if (isset($filters['start_date'])) {
            $query->whereDate('start_date', '>=', $filters['start_date']);
        }
        
        if (isset($filters['end_date'])) {
            $query->whereDate('end_date', '<=', $filters['end_date']);
        }
        
        return $query;
    }

    public function getStatusAttribute($value)
    {
        return ucfirst($value);
    }

    public function getLeaveTypeAttribute($value)
    {
        return ucfirst($value);
    }

    public function getDurationAttribute()
    {
        if (!$this->start_date || !$this->end_date) {
            return 'N/A';
        }

        $start = \Carbon\Carbon::parse($this->start_date);
        $end = \Carbon\Carbon::parse($this->end_date);
        $days = $start->diffInDays($end) + 1;

        return $days . ' ' . ($days == 1 ? 'day' : 'days');
    }

    public function isOverlapping($startDate, $endDate)
    {
        return $this->where(function($query) use ($startDate, $endDate) {
            $query->where(function($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function($q) use ($startDate, $endDate) {
                      $q->where('start_date', '<=', $startDate)
                        ->where('end_date', '>=', $endDate);
                  });
            });
        })
        ->where('status', '!=', 'rejected')
        ->exists();
    }
}
