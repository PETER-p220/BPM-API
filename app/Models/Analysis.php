<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Analysis extends Model
{
    protected $table = 'analyses';
    protected $primaryKey = 'analysis_id';
    
    protected $fillable = [
        'project_id',
        'tender_id',
        'user_id',
        'serial_number',
        'item_description',
        'quoted_quantity',
        'quoted_unit',
        'quoted_rate',
        'quoted_amount',
        'quantity',
        'rate',
        'amount',
        'source',
        'urgent_status',
        'total_amount_vat_excl',
        'total_amount_vat_incl',
        'total_amount_needed',
        'site_contingency',
        'total_investment',
        'projected_profit',
        'projected_profit_percentage',
        'status',
        'reason_for_reject'
    ];

    protected $casts = [
        'quoted_quantity' => 'integer',
        'quoted_rate' => 'decimal:2',
        'quoted_amount' => 'decimal:2',
        'quantity' => 'integer',
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'total_amount_vat_excl' => 'decimal:2',
        'total_amount_vat_incl' => 'decimal:2',
        'total_amount_needed' => 'decimal:2',
        'site_contingency' => 'decimal:2',
        'total_investment' => 'decimal:2',
        'projected_profit' => 'decimal:2',
        'projected_profit_percentage' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id','user_id');
    }

    public function tender()
    {
        return $this->belongsTo(Tender::class, 'tender_id', 'tender_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }

    public function items()
{
    return $this->hasMany(Items::class, 'analysis_id', 'analysis_id');
}
}