<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE assign_tenders MODIFY COLUMN is_assigned ENUM('on-progress','quoted','approved','rejected','submitted') DEFAULT 'on-progress'");

        Schema::table('assign_tenders', function (Blueprint $table) {
            $table->text('ceo_comment')->nullable()->after('is_assigned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assign_tenders', function (Blueprint $table) {
            $table->dropColumn('ceo_comment');
        });

        DB::statement("ALTER TABLE assign_tenders MODIFY COLUMN is_assigned ENUM('on-progress','submitted') DEFAULT 'on-progress'");
    }
};
