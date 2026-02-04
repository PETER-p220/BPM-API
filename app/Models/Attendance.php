<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

     protected $table = 'attendances';

     protected $primaryKey = 'att_id';


     protected $fillable = [
          'user_id',
        'attenda_id',
        'is_present',
        'if_not_present_and_have_reason',
    ];


    public function user()
    {
        return $this->belongsTo(User::class,'user_id','user_id');
    }
    
}
