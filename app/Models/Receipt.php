<?php
// app/Models/Receipt.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    use HasFactory;

    protected $primaryKey = 'receipt_id';

    protected $fillable = [
        'receipt_file',
        'description',
        'user_id',
        'accountant_id',
        'is_approved',
    ];

    /**
     * Get the user that submitted the receipt.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id','user_id');
    }

    /**
     * Get the accountant who is handling the receipt.
     */
    public function accountant()
    {
        return $this->belongsTo(User::class, 'accountant_id','user_id');
    }
}
