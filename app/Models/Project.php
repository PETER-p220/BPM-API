<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $primaryKey = 'project_id';

    protected $fillable = [
        'project_name',
        'tender_id',
        'user_id',
        'contract_id',
        'member_id',
        'created_by',
        'contract',
        'project_status',
        'follow_up',
        'start_date',
        'end_date',
        'extended_date',
        'budget',
    ];

    protected $casts = [
        'member_id' => 'array', // Cast JSON to array
        'start_date' => 'date',
        'end_date' => 'date',
        'extended_date' => 'date',
        'budget' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function tender()
    {
        return $this->belongsTo(Tender::class, 'tender_id', 'tender_id');
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id', 'contract_id');
    }
}