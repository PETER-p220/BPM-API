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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
        $table->string('payment');
        $table->string('item');
        $table->string('ref_number');
        $table->decimal('amount');
        $table->integer('department_id');
        $table->string('iscreated_by');
        $table->text('description');
        $table->unsignedBigInteger('project_id');
        $table->string('project_name');
        $table->string('tender_id')->nullable();
        $table->decimal('budget')->nullable();
        $table->string('contract')->nullable();
        $table->string('created_by')->nullable();
        $table->date('start_date')->nullable();
        $table->date('end_date')->nullable();
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
