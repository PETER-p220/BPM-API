<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestForPurchase extends Model
{
    protected $table = 'request_for_purchases';
    protected $primaryKey = 'request_for_id';
    protected $fillable = [
        'project_id',
        'analysis_id',
        'user_id',
        'item_description',
        'status',
        'quantity_purchased',
        'amount_purchased',
        'VendorName',
        'VendorAccountNumber',
        'VendorContact',
        'rejection_reason'
    ];

    protected $casts = [
        'quantity_purchased' => 'integer',
        'amount_purchased' => 'decimal:2',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }

    public function analysis()
    {
        return $this->belongsTo(Analysis::class, 'analysis_id', 'analysis_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}