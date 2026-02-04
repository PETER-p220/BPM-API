<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tender extends Model
{
    use HasFactory;

    protected $primaryKey = 'tender_id';
    protected $table = 'tenders';

    protected $fillable = [
        'title',
        'tender_type',
        'tender_source',
        'procurement_entity',
        'tender_number',
        'user_id',
        'attachment',
        'date_of_Publication',
        'bid_submission',
        'expired_at',
    ];

    public function assignedTenders()
    {
        return $this->hasMany(AssignTender::class, 'user_id', 'user_id');
    }
}