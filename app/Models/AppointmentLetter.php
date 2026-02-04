<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentLetter extends Model
{
    protected $table = 'appointment_letters';
    protected $primaryKey = 'letter_id';
    protected $fillable = ['user_id', 'tender_id', 'letter_file', 'status'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function tender()
    {
        return $this->belongsTo(Tender::class, 'tender_id', 'tender_id');
    }
}