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
        Schema::table('meeting_minutes', function (Blueprint $table) {
            // Make minute_point nullable to support HR meeting minutes
            $table->json('minute_point')->nullable()->change();
            $table->integer('project_id')->nullable()->change();
            $table->integer('capture_logged_user_id')->nullable()->change();
            $table->json('if_more_detail')->nullable()->change();
            $table->foreignId('department_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meeting_minutes', function (Blueprint $table) {
            // Revert to original requirements
            $table->json('minute_point')->nullable(false)->change();
            $table->integer('project_id')->nullable(false)->change();
            $table->integer('capture_logged_user_id')->nullable(false)->change();
            $table->json('if_more_detail')->nullable(false)->change();
            $table->foreignId('department_id')->nullable(false)->change();
        });
    }
};
