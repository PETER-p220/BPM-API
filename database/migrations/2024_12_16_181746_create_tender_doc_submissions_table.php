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
       Schema::create('tender_doc_submissions', function (Blueprint $table) {
    $table->id('submission_id');
    $table->unsignedBigInteger('tender_id');
    $table->unsignedBigInteger('user_id');
    $table->string('submission_document'); // Single string definition
    $table->string('qualifications'); 
    $table->enum('is_submitted', ['submitted'])->nullable();
    $table->timestamps();
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tender_doc_submissions');
    }
};
