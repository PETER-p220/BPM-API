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
        Schema::create('request_for_purchases', function (Blueprint $table) {
            $table->id('request_for_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('analysis_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->integer('quantity_purchased');
            $table->decimal('amount_purchased', 10, 2);
            $table->string('VendorName');
            $table->string('VendorAccountNumber');
            $table->string('VendorContact');
            $table->string('rejection_reason')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_for_purchases');
    }
};
