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
        Schema::create('attendances', function (Blueprint $table) {
           $table->id('att_id');
            $table->foreignId('user_id'); // The logged-in user
            $table->foreignId('attenda_id'); // The member who attended
            $table->enum('is_present', ['present', 'not-present']);
            $table->text('if_not_present_and_have_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
