<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestForProject extends Model
{
    use HasFactory;

     // Specify the table name explicitly
    protected $table = 'request_for_projects'; // Updated table name

        protected $primaryKey = 'request_id';

    // Define the fillable fields
    protected $fillable = [
        'item',
        'amount_requested',
        'user_id',
        'tender_id',
        'is_approved',
        'vender',
        'vendor_account_number',
        'vender_account_name'
    ];


    
    public function tender()
    {
        return $this->belongsTo(Tender::class, 'tender_id', 'tender_id');
    }

     public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }


    
}
