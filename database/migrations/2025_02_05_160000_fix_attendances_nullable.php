<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Make attenda_id nullable to support both HR and accountant attendance
            $table->unsignedBigInteger('attenda_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Revert attenda_id back to not nullable
            $table->unsignedBigInteger('attenda_id')->nullable(false)->change();
        });
    }
};
