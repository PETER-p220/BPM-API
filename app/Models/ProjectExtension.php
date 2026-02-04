<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectExtension extends Model
{
    protected $table = 'project_extensions';
    protected $primaryKey = 'extension_id';
    protected $fillable = ['project_id', 'extended_date', 'extend_letter_file'];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }
}