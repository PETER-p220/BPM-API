<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('performance_evaluations', function (Blueprint $table) {
            // Add foreign key constraints
            $table->foreign('employee_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('reviewer_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('approved_by')->references('user_id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('performance_evaluations', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['reviewer_id']);
            $table->dropForeign(['approved_by']);
        });
    }
};
