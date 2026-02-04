<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeetingMinute extends Model
{
    use HasFactory;

      protected $table = 'meeting_minutes';

     protected $primaryKey = 'minutes_id';

     protected $fillable = [
        'user_id',
        'minute_point',
        'if_more_detail',
        'department_id',
        'project_id',
        'capture_logged_user_id'

    ];



      public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id'); // Link 'user_id' to 'id'
    }


      public function department()
    {
        return $this->belongsTo(Department::class, 'department_id','department_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id','project_id');
    }

    public function loggedUser()
    {
        return $this->belongsTo(User::class, 'capture_logged_user_id', 'user_id'); // Link 'user_id' to 'id'
    }

}
