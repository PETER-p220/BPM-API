<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('financial_records', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('description');
            $table->string('reference')->nullable();
            $table->enum('type', ['income', 'expense']);
            $table->enum('category', ['sales', 'services', 'operations', 'salary', 'utilities', 'maintenance']);
            $table->decimal('amount', 15, 2);
            $table->enum('status', ['pending', 'verified', 'approved'])->default('pending');
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('approved_by')->references('user_id')->on('users')->onDelete('set null');

            $table->index(['type', 'status']);
            $table->index(['date']);
            $table->index(['category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('financial_records');
    }
};
