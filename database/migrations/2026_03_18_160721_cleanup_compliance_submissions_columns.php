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
            // Drop duplicate columns
            if (Schema::hasColumn('compliance_submissions', 'reference')) {
                $table->dropColumn('reference');
            }
            if (Schema::hasColumn('compliance_submissions', 'review_comments')) {
                $table->dropColumn('review_comments');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('compliance_submissions', function (Blueprint $table) {
            // Restore columns if needed
            if (!Schema::hasColumn('compliance_submissions', 'reference')) {
                $table->string('reference')->nullable()->after('title');
            }
            if (!Schema::hasColumn('compliance_submissions', 'review_comments')) {
                $table->text('review_comments')->nullable()->after('status');
            }
        });
    }
};
