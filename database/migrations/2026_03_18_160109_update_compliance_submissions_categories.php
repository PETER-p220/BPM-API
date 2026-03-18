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
        Schema::table('compliance_submissions', function (Blueprint $table) {
            // Drop the existing enum column and recreate with new values
            $table->dropColumn('category');
        });
        
        Schema::table('compliance_submissions', function (Blueprint $table) {
            $table->enum('category', ['financial', 'procurement', 'ethical', 'safety', 'other', 'operational', 'legal', 'environmental', 'hr'])->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('compliance_submissions', function (Blueprint $table) {
            $table->dropColumn('category');
        });
        
        Schema::table('compliance_submissions', function (Blueprint $table) {
            $table->enum('category', ['financial', 'procurement', 'ethical', 'safety', 'other'])->after('description');
        });
    }
};
