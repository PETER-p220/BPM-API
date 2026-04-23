<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE assign_tenders MODIFY COLUMN is_assigned ENUM('on-progress','quoted','approved','rejected','submitted','awarded') DEFAULT 'on-progress'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE assign_tenders MODIFY COLUMN is_assigned ENUM('on-progress','quoted','approved','rejected','submitted') DEFAULT 'on-progress'");
    }
};