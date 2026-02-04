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
        Schema::create('award_letters', function (Blueprint $table) {
            $table->id('award_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('tender_id');
            $table->string('awardletter_file'); // Cloudinary URL
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('award_letters');
    }
};
