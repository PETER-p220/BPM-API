<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Directly update the enum column using raw SQL
        DB::statement("ALTER TABLE performance_evaluations MODIFY COLUMN status ENUM('draft', 'submitted', 'approved', 'rejected', 'outstanding', 'exceeds_expectations', 'meets_expectations', 'needs_improvement', 'unsatisfactory') DEFAULT 'draft'");
    }

    public function down()
    {
        // Revert to original enum values
        DB::statement("ALTER TABLE performance_evaluations MODIFY COLUMN status ENUM('draft', 'submitted', 'approved', 'rejected') DEFAULT 'draft'");
    }
};
