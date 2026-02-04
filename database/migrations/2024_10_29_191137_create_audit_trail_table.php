<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuditTrailTable extends Migration
{
    public function up()
    {
        Schema::create('audit_trail', function (Blueprint $table) {
             $table->id(); // Primary key
        $table->unsignedBigInteger('user_id'); // User ID
        $table->string('email'); // Email
        $table->unsignedBigInteger('role_id'); // Role ID
        $table->string('action'); // Action (e.g., login, logout)
        $table->timestamps(); // Created_at and Updated_at
        });
    }

    public function down()
    {
        Schema::dropIfExists('audit_trail');
    }
}
