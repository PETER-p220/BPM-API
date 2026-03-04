<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('performance_evaluations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('employee_id');
            $table->unsignedInteger('reviewer_id');
            $table->string('review_period', 255);
            
            // Performance criteria ratings (1-5 scale)
            $table->tinyInteger('job_knowledge')->unsigned();
            $table->tinyInteger('work_quality')->unsigned();
            $table->tinyInteger('productivity')->unsigned();
            $table->tinyInteger('communication')->unsigned();
            $table->tinyInteger('teamwork')->unsigned();
            $table->tinyInteger('initiative')->unsigned();
            
            // Comments for each criteria
            $table->text('job_knowledge_comments')->nullable();
            $table->text('work_quality_comments')->nullable();
            $table->text('productivity_comments')->nullable();
            $table->text('communication_comments')->nullable();
            $table->text('teamwork_comments')->nullable();
            $table->text('initiative_comments')->nullable();
            
            // Overall rating and comments
            $table->tinyInteger('overall_rating')->unsigned();
            $table->text('overall_comments');
            $table->text('goals_next_period')->nullable();
            $table->date('review_date');
            
            // Status and metadata
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['employee_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('review_period');
        });
    }

    public function down()
    {
        Schema::dropIfExists('performance_evaluations');
    }
};
