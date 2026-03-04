<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceEvaluation extends Model
{
    use HasFactory;

    protected $table = 'performance_evaluations';

    protected $primaryKey = 'id';

    protected $fillable = [
        'employee_id',
        'reviewer_id',
        'review_period',
        'job_knowledge',
        'work_quality',
        'productivity',
        'communication',
        'teamwork',
        'initiative',
        'overall_rating',
        'overall_comments',
        'review_date',
        'goals_next_period',
        'job_knowledge_comments',
        'work_quality_comments',
        'productivity_comments',
        'communication_comments',
        'teamwork_comments',
        'initiative_comments',
        'status',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'review_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'job_knowledge' => 'integer',
        'work_quality' => 'integer',
        'productivity' => 'integer',
        'communication' => 'integer',
        'teamwork' => 'integer',
        'initiative' => 'integer',
        'overall_rating' => 'integer'
    ];

    /**
     * Get the employee being evaluated
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id', 'user_id');
    }

    /**
     * Get the reviewer who conducted the evaluation
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id', 'user_id');
    }

    /**
     * Get the calculated average of all criteria ratings
     */
    public function getCalculatedRatingAttribute(): float
    {
        $ratings = [
            $this->job_knowledge,
            $this->work_quality,
            $this->productivity,
            $this->communication,
            $this->teamwork,
            $this->initiative
        ];

        return collect($ratings)->filter()->avg() ?? 0;
    }

    /**
     * Get the formatted status label
     */
    public function getFormattedStatusAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->status));
    }

    /**
     * Get the performance level based on rating
     */
    public function getPerformanceLevelAttribute(): string
    {
        if ($this->overall_rating >= 4.5) {
            return 'Outstanding';
        } elseif ($this->overall_rating >= 3.5) {
            return 'Exceeds Expectations';
        } elseif ($this->overall_rating >= 2.5) {
            return 'Meets Expectations';
        } elseif ($this->overall_rating >= 1.5) {
            return 'Needs Improvement';
        } else {
            return 'Unsatisfactory';
        }
    }

    /**
     * Scope to get evaluations by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get evaluations by review period
     */
    public function scopeByPeriod($query, $period)
    {
        return $query->where('review_period', 'like', '%' . $period . '%');
    }

    /**
     * Scope to get evaluations by department
     */
    public function scopeByDepartment($query, $department)
    {
        return $query->whereHas('employee', function ($q) use ($department) {
            $q->where('department', $department);
        });
    }

    /**
     * Scope to get evaluations within date range
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('review_date', [$startDate, $endDate]);
    }

    /**
     * Scope to get high performers (rating >= 4)
     */
    public function scopeHighPerformers($query)
    {
        return $query->where('overall_rating', '>=', 4);
    }

    /**
     * Scope to get evaluations needing improvement (rating < 3)
     */
    public function scopeNeedsImprovement($query)
    {
        return $query->where('overall_rating', '<', 3);
    }
}
