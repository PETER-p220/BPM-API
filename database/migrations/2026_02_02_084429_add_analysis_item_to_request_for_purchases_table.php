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
        Schema::table('request_for_purchases', function (Blueprint $table) {
            //
            $table->string('analysis_item')->after('analysis_id')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_for_purchases', function (Blueprint $table) {
            //
            $table->dropColumn('analysis_item');
        });
    }
};
