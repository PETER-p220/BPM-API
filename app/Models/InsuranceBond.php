<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsuranceBond extends Model
{
    protected $table = 'insurance_bonds';
    protected $primaryKey = 'insurance_id';
    protected $fillable = ['user_id', 'tender_id', 'insurance_file','receiver_email'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function tender()
    {
        return $this->belongsTo(Tender::class, 'tender_id', 'tender_id');
    }
}