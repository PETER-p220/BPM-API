<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtendRequest extends Model
{
    protected $table = 'extend_requests';
    protected $primaryKey = 'extend_id';
    protected $fillable = [
        'project_id',
        'user_id',
        'analysis_id',
        'quantity_extended',
        'amount_extended',
        'reason_for_extend',
        'rejection_reason',
        'status',
    ];

    protected $casts = [
        'quantity_extended' => 'integer',
        'amount_extended' => 'decimal:2',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function analysis()
    {
        return $this->belongsTo(Analysis::class, 'analysis_id', 'analysis_id');
    }
}