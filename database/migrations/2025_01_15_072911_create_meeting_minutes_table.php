<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('meeting_minutes', function (Blueprint $table) {
          $table->id('minutes_id');
            $table->foreignId('user_id');
            $table->json('minute_point');
            $table->integer('project_id');
            $table->integer('capture_logged_user_id');
            $table->json('if_more_detail')->nullable();
            $table->foreignId('department_id');
            $table->timestamps();
        });
    }
 


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_minutes');
    }
};
