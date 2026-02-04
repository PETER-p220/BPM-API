<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceSchedule extends Model
{
    protected $table = 'price_schedules';
    protected $primaryKey = 'price_schedule_id';
    
    protected $fillable = [
        'tender_id',
        'user_id',
        'serial_number',
        '',
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
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function tender()
    {
        return $this->belongsTo(Tender::class, 'tender_id', 'tender_id');
    }
}