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
        Schema::create('payments', function (Blueprint $table) {
              $table->id('payment_id');
            $table->integer('user_id'); // Reference to the logged-in user
            $table->integer('project_id'); // Reference to the project
            $table->string('amount_paid'); // Amount paid
            $table->enum('payment_status', ['partial-payed', 'total-payed'])->nullable(); // Payment status (partial/total)
            $table->enum('payment_category', ['credit', 'cash']); // Payment category
            $table->string('is_approved')->default('pending'); // SI approved status
            $table->string('if_debt')->default('no-debt'); // Indicates if there is an outstanding debt
            $table->text('description')->nullable(); // Optional description
            $table->string('client_name'); // Client name
            $table->string('ref_number')->nullable(); // Reference number
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
