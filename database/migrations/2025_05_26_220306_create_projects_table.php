<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id('project_id');
            $table->string('project_name');
            $table->unsignedBigInteger('tender_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('contract_id');
            $table->json('member_id')->nullable(); // Stores array of user IDs
            $table->string('created_by');
            $table->string('contract')->nullable(); // Stores Cloudinary URL for contract PDF
            $table->string('project_status')->default('on-progress'); // Enum: on-progress, completed, failed
            $table->string('follow_up')->nullable(); // Enum: on-progress, completed
            $table->date('start_date');
            $table->date('end_date');
            $table->date('extended_date')->nullable();
            $table->decimal('budget', 15, 2)->nullable(); // Assuming budget is a decimal
            $table->unsignedBigInteger('is_sent_to_hod')->nullable(); // Stores user_id of HOD
            $table->timestamps();

           
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};