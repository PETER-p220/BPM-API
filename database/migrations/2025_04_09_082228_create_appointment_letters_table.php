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
        Schema::create('appointment_letters', function (Blueprint $table) {
            $table->id('letter_id');
            $table->unsignedBigInteger('user_id'); // Manually entered user_id
            $table->unsignedBigInteger('tender_id');
            $table->string('letter_file'); // Cloudinary URL
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_letters');
    }
};
