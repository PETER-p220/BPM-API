<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntentionToAward extends Model
{
    protected $table = 'intention_to_awards';
    protected $primaryKey = 'intention_id';
    protected $fillable = ['user_id', 'tender_id', 'intention_file'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function tender()
    {
        return $this->belongsTo(Tender::class, 'tender_id', 'tender_id');
    }
}