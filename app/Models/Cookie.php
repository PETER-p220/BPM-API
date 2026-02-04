<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cookie extends Model
{
    use HasFactory;


     // Specify the table if it's not the plural form of the model name
     protected $table = 'cookies';

     // Specify which attributes should be mass assignable
     protected $fillable = [
         'user_id',       // The ID of the user associated with the cookie
         'cookie_name',   // The name of the cookie
         'cookie_value',  // The value of the cookie
     ];
}
