<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AwardedTender extends Model
{
    protected $table = 'awarded_tenders';
    protected $primaryKey = 'award_id';

    protected $fillable = [
        'tender_id',
        'user_id',
        'id_of_who_post_award',
        'awarded_document',
        'is_sent',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function tender()
    {
        return $this->belongsTo(Tender::class, 'tender_id', 'tender_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'id_of_who_post_award', 'user_id');
    }
}
