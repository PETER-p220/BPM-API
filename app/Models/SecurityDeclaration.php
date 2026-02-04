<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityDeclaration extends Model
{
    protected $table = 'security_declarations';
    protected $primaryKey = 'declaration_id';
    protected $fillable = ['user_id', 'tender_id','receiver_email', 'declaration_file'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function tender()
    {
        return $this->belongsTo(Tender::class, 'tender_id', 'tender_id');
    }
}