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
        Schema::create('tenders', function (Blueprint $table) {
            $table->id('tender_id');
            $table->string('title');
            $table->string('tender_type');
            $table->string('tender_source');
            $table->string('procurement_entity');
            $table->string('tender_number');
             $table->string('user_id');
            $table->string('attachment');
            $table->string('date_of_Publication');
            $table->string('expired_at');
            $table->string('bid_submission');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenders');
    }
};
