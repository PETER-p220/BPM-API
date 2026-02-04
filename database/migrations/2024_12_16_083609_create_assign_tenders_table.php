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
        Schema::create('assign_tenders', function (Blueprint $table) {
           $table->id('assign_id');
        $table->foreignId('tender_id');
        $table->enum('is_assigned', ['on-progress', 'submitted'])->default('on-progress');
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assign_tenders');
    }
};
