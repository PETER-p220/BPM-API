<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectAnalysis extends Model
{
   use HasFactory;

    protected $primaryKey = 'project_analysis_id';

     protected $table = 'project_analyses';

    protected $fillable = [
        'tender_id',
        'user_id',
        'reason_for_reject',
        'status',
        'department_id',
        'analysis_file',
        'amount_required_for_request'
    ];


    // Relationship with User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id','user_id');
    }


    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id','department_id');
    }



    // Relationship with Tender
    public function tender()
    {
        return $this->belongsTo(Tender::class, 'tender_id','tender_id');
    }
}
