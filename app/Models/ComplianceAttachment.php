<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'compliance_submission_id',
        'file_name',
        'file_path',
        'file_size',
        'file_type',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'file_size' => 'integer',
    ];

    /**
     * Get the compliance submission that owns the attachment.
     */
    public function complianceSubmission(): BelongsTo
    {
        return $this->belongsTo(ComplianceSubmission::class);
    }
}
