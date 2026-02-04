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
        Schema::create('awarded_tenders', function (Blueprint $table) {
            $table->id('award_id');
            $table->foreignId('tender_id');
            $table->foreignId('user_id');
            $table->foreignId('id_of_who_post_award');
            $table->string('awarded_document');
            $table->enum('is_sent', ['sent', 'not-sent'])->default('not-sent');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('awarded_tenders');
    }
};
