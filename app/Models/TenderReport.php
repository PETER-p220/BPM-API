<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenderReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'tender_id',
        'user_id',
        'report_type',
        'reason',
        'recommendations',
        'supporting_document',
        'reported_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the tender that owns the report.
     */
    public function tender()
    {
        return $this->belongsTo(Tender::class, 'tender_id', 'tender_id');
    }

    /**
     * Get the user who created the report.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Check if the report has a supporting document.
     */
    public function getSupportingDocumentAttribute($value)
    {
        return $value ? asset('storage/' . $value) : null;
    }
}
