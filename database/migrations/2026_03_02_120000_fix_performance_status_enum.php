<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('performance_evaluations', function (Blueprint $table) {
            // Modify existing status column to include all possible values
            $table->enum('status', [
                'draft', 
                'submitted', 
                'approved', 
                'rejected',
                'outstanding',
                'exceeds_expectations', 
                'meets_expectations',
                'needs_improvement',
                'unsatisfactory'
            ])->default('draft')->change();
        });
    }

    public function down()
    {
        Schema::table('performance_evaluations', function (Blueprint $table) {
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft')->change();
            $table->dropColumn('status');
            
            // Recreate original enum
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');
        });
    }
};
