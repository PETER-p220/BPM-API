<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectActivity extends Model
{
    use HasFactory;


    protected $table = 'project_activities';

    protected $primaryKey = 'activity_id';

    protected $fillable = [
        'activity_category',
        'user_id',
        'project_id',
        'department_id',
        'description',
        'activity_photo',
        'activity_file',
        'task_status',
        'is_viewed',
        'iscreated_by'
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
}
