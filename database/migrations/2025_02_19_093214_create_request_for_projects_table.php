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
        Schema::create('request_for_projects', function (Blueprint $table) {
            $table->id('request_id'); // Primary key
            $table->string('item');
            $table->decimal('amount_requested', 15, 2);
            $table->foreignId('user_id'); 
            $table->foreignId('tender_id'); 
            $table->enum('is_approved', ['pending', 'approved', 'rejected'])->default('pending'); 
            $table->string('vender'); 
            $table->string('vendor_account_number');
            $table->string('vender_account_name');
            $table->timestamps(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_for_projects');
    }
};
