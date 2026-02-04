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
        Schema::create('security_declarations', function (Blueprint $table) {
            $table->id('declaration_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('tender_id');
            $table->string('declaration_file'); // Cloudinary URL
            $table->unsignedBigInteger('receiver_email');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_declarations');
    }
};
