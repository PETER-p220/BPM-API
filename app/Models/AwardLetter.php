<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AwardLetter extends Model
{
    protected $table = 'award_letters';
    protected $primaryKey = 'award_id';
    protected $fillable = ['user_id', 'tender_id', 'awardletter_file'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function tender()
    {
        return $this->belongsTo(Tender::class, 'tender_id', 'tender_id');
    }
}