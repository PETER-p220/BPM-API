<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssignTender extends Model
{
    use HasFactory;

    protected $table = 'assign_tenders';

     protected $primaryKey = 'assign_id';

    protected $fillable = [
        'tender_id',
        'user_id',
        'is_assigned'
    ];


    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id'); // Link 'user_id' to 'id'
    }

    public function tender()
    {
        return $this->belongsTo(Tender::class, 'tender_id', 'tender_id');
    }
}
