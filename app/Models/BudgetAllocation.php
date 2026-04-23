<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'allocation_type',
        'project_id',
        'award_id',
        'allocated_amount',
        'spent_amount',
        'period',
        'description',
        'fiscal_year',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'rejection_reason'
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'spent_amount' => 'decimal:2',
        'approved_at' => 'datetime'
    ];

    public const TYPE_DEPARTMENT = 'department';
    public const TYPE_PROJECT = 'project';
    public const TYPE_AWARDED_TENDER = 'awarded_tender';

    /**
     * Get the department this budget is allocated to
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }

    public function awardedTender(): BelongsTo
    {
        return $this->belongsTo(AwardedTender::class, 'award_id', 'award_id');
    }

    /**
     * Get the user this budget is allocated to (fallback for backward compatibility)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'department_id', 'user_id');
    }

    /**
     * Get the user who created this budget (accountant)
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    /**
     * Get the user who approved this budget (CEO)
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by', 'user_id');
    }

    /**
     * Update spent amount based on actual project spending
     */
    public function updateSpentAmount(): void
    {
        // For now, set spent_amount to 0 to avoid errors
        // TODO: Implement proper project spending calculation
        $this->spent_amount = 0;
        $this->save();
    }

    /**
     * Calculate utilization percentage
     */
    public function getUtilizationPercentageAttribute(): float
    {
        if ($this->allocated_amount == 0) {
            return 0;
        }
        
        return round(($this->spent_amount / $this->allocated_amount) * 100, 1);
    }

    /**
     * Get remaining budget amount
     */
    public function getRemainingAmountAttribute(): float
    {
        return $this->allocated_amount - $this->spent_amount;
    }

    /**
     * Get status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'text-amber-600 bg-amber-100/30',
            'approved' => 'text-green-600 bg-green-100/30',
            'rejected' => 'text-red-600 bg-red-100/30',
            'active' => 'text-blue-600 bg-blue-100/30',
            default => 'text-slate-600 bg-slate-100/30'
        };
    }

    public function getTargetTypeLabelAttribute(): string
    {
        return match ($this->allocation_type ?? self::TYPE_DEPARTMENT) {
            self::TYPE_PROJECT => 'Project',
            self::TYPE_AWARDED_TENDER => 'Awarded Tender',
            default => 'Department',
        };
    }

    public function getTargetLabelAttribute(): string
    {
        return match ($this->allocation_type ?? self::TYPE_DEPARTMENT) {
            self::TYPE_PROJECT => $this->project?->project_name ?? 'Unknown Project',
            self::TYPE_AWARDED_TENDER => $this->awardedTender?->tender?->title ?? 'Unknown Awarded Tender',
            default => $this->department?->name ?? $this->user?->name ?? 'Unassigned Department',
        };
    }
}
