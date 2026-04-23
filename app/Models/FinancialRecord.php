<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'date',
        'description',
        'reference',
        'type',
        'category',
        'amount',
        'status',
        'receipt_file',
        'created_by',
        'approved_by',
        'approved_at'
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'approved_at' => 'datetime'
    ];

    protected $dates = [
        'date',
        'created_at',
        'updated_at',
        'deleted_at',
        'approved_at'
    ];

    /**
     * Get the user who created the record.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    /**
     * Get the user who approved the record.
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by', 'user_id');
    }

    /**
     * Scope to get only income records.
     */
    public function scopeIncome($query)
    {
        return $query->where('type', 'income');
    }

    /**
     * Scope to get only expense records.
     */
    public function scopeExpense($query)
    {
        return $query->where('type', 'expense');
    }

    /**
     * Scope to get records by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get records by category.
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to get records for a specific date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Get formatted amount.
     */
    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2);
    }

    /**
     * Get amount with currency symbol.
     */
    public function getAmountWithCurrencyAttribute()
    {
        return 'TZS ' . number_format($this->amount, 0);
    }

    /**
     * Check if record is income.
     */
    public function isIncome()
    {
        return $this->type === 'income';
    }

    /**
     * Check if record is expense.
     */
    public function isExpense()
    {
        return $this->type === 'expense';
    }

    /**
     * Check if record is approved.
     */
    public function isApproved()
    {
        return $this->status === 'approved';
    }

    /**
     * Check if record is pending.
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    /**
     * Check if record is verified.
     */
    public function isVerified()
    {
        return $this->status === 'verified';
    }
}
