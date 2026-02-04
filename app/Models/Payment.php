<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;


     // Define the table name if it differs from the default
    protected $table = 'payments';

    // Specify the custom primary key
    protected $primaryKey = 'payment_id';

    // Define the fillable fields for mass assignment
    protected $fillable = [
        'user_id',
        'project_id',
        'amount_paid',
        'payment_status',
        'payment_category',
        'is_approved',
        'if_debt', // Add 'if_debt' to the fillable fields
        'description',
        'client_name',
        'ref_number',
    ];


     public function project()
    {
        return $this->belongsTo(Project::class, 'project_id','project_id');
    }

     public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
