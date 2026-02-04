<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    use HasFactory;

      protected $table = 'chats';
    protected $primaryKey = 'chat_id';

    protected $fillable = [
        'title',
        'description',
        'update_photo',
        'update_file',
        'user_id',
    ];


     public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

}
