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
        Schema::create('project_activities', function (Blueprint $table) {
            $table->id('activity_id');
            $table->string('activity_category');
            $table->integer('user_id');
            $table->string('iscreated_by');
            $table->integer('project_id');
            $table->integer('department_id');
            $table->text('description')->nullable();
            $table->string('activity_photo')->nullable();
            $table->string('activity_file')->nullable();
            $table->string('task_status')->default('pending');
              $table->string('is_viewed')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_activities');
    }
};
