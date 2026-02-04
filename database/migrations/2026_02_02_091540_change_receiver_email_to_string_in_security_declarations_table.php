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
        Schema::table('security_declarations', function (Blueprint $table) {
            //
            $table->string('receiver_email', 255)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('security_declarations', function (Blueprint $table) {
            //
            $table->string('receiver_email', 255)->change();
        });
    }
};
