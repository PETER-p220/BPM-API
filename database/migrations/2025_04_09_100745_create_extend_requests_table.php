<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extend_requests', function (Blueprint $table) {
            $table->id('extend_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('user_id'); // Creator via Auth::id()
            $table->unsignedBigInteger('analysis_id');
            $table->integer('quantity_extended')->nullable(); // Fixed: nullable instead of invalid default
            $table->decimal('amount_extended', 10, 2);
            $table->text('reason_for_extend');
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extend_requests');
    }
};