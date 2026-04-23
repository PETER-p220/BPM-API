<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE budget_allocations MODIFY department_id INT UNSIGNED NULL");
        DB::statement("ALTER TABLE budget_allocations MODIFY fiscal_year VARCHAR(4) NOT NULL");
        DB::statement("ALTER TABLE budget_allocations ADD COLUMN allocation_type ENUM('department','project','awarded_tender') NOT NULL DEFAULT 'department' AFTER department_id");
        DB::statement("ALTER TABLE budget_allocations ADD COLUMN project_id BIGINT UNSIGNED NULL AFTER allocation_type");
        DB::statement("ALTER TABLE budget_allocations ADD COLUMN award_id BIGINT UNSIGNED NULL AFTER project_id");

        Schema::table('budget_allocations', function (Blueprint $table) {
            $table->index(['allocation_type', 'status'], 'budget_allocations_type_status_idx');
            $table->index('project_id', 'budget_allocations_project_idx');
            $table->index('award_id', 'budget_allocations_award_idx');
        });
    }

    public function down(): void
    {
        Schema::table('budget_allocations', function (Blueprint $table) {
            $table->dropIndex('budget_allocations_type_status_idx');
            $table->dropIndex('budget_allocations_project_idx');
            $table->dropIndex('budget_allocations_award_idx');
        });

        DB::statement("ALTER TABLE budget_allocations DROP COLUMN award_id");
        DB::statement("ALTER TABLE budget_allocations DROP COLUMN project_id");
        DB::statement("ALTER TABLE budget_allocations DROP COLUMN allocation_type");
        DB::statement("ALTER TABLE budget_allocations MODIFY department_id INT UNSIGNED NOT NULL");
        DB::statement("ALTER TABLE budget_allocations MODIFY fiscal_year ENUM('2022','2023','2024','2025','2026','2027') NOT NULL");
    }
};