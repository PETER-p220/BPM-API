<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;
    protected $fillable = [
        'payment',
        'item',
        'ref_number',
        'amount',
        'department_id',
        'iscreated_by',
        'description',
        'project_id',
        'project_name',
        'tender_id',
        'budget',
        'contract',
        'created_by',
        'start_date',
        'end_date',
    ];

     public function department()
    {
        return $this->belongsTo(Department::class, 'department_id','department_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id','project_id');
    }

     public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

   public function requestForProject()
    {
        return $this->belongsTo(RequestForProject::class, 'request_id', 'request_id');
    }

     public function tender()
    {
        return $this->belongsTo(Tender::class, 'tender_id', 'tender_id');
    }
}
