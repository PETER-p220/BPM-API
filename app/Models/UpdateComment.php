<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UpdateComment extends Model
{
    use HasFactory;

    protected $table = 'update_comments';

    protected $fillable = [
        'chat_id',
        'ceo_id',
        'comment',
        'is_read',
    ];

    public function chat()
    {
        return $this->belongsTo(Chat::class, 'chat_id', 'chat_id');
    }

    public function ceo()
    {
        return $this->belongsTo(User::class, 'ceo_id', 'user_id');
    }
}
