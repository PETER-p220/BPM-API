<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('employee_id');
            $table->enum('leave_type', ['sick', 'vacation', 'maternity', 'paternity', 'emergency', 'unpaid']);
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('days');
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->unsignedInteger('requested_by');
            $table->unsignedInteger('approver_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();

            // Foreign key constraints (only for numeric fields)
            $table->foreign('employee_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('requested_by')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('approver_id')->references('user_id')->on('users')->onDelete('set null');

            // Add indexes
            $table->index(['employee_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('leave_type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('leaves');
    }
};
