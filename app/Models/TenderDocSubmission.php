<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenderDocSubmission extends Model
{
    use HasFactory;

     protected $table = 'tender_doc_submissions';
    protected $primaryKey = 'submission_id';

    protected $fillable = [
        'tender_id',
        'user_id',
        'submission_document',
        'is_submitted'
    
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
