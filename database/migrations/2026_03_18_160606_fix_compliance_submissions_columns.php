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
            // Add reference_number column if it doesn't exist
            if (!Schema::hasColumn('compliance_submissions', 'reference_number')) {
                $table->string('reference_number')->unique()->after('title');
            }
            
            // Add review_notes column if it doesn't exist
            if (!Schema::hasColumn('compliance_submissions', 'review_notes')) {
                $table->text('review_notes')->nullable()->after('status');
            }
            
            // Rename reference to reference_number if needed, or drop if duplicate
            if (Schema::hasColumn('compliance_submissions', 'reference') && !Schema::hasColumn('compliance_submissions', 'reference_number')) {
                $table->renameColumn('reference', 'reference_number');
            } elseif (Schema::hasColumn('compliance_submissions', 'reference') && Schema::hasColumn('compliance_submissions', 'reference_number')) {
                $table->dropColumn('reference');
            }
            
            // Rename review_comments to review_notes if needed, or drop if duplicate
            if (Schema::hasColumn('compliance_submissions', 'review_comments') && !Schema::hasColumn('compliance_submissions', 'review_notes')) {
                $table->renameColumn('review_comments', 'review_notes');
            } elseif (Schema::hasColumn('compliance_submissions', 'review_comments') && Schema::hasColumn('compliance_submissions', 'review_notes')) {
                $table->dropColumn('review_comments');
            }
            
            // Add indexes if they don't exist
            $table->index('reference_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('compliance_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('compliance_submissions', 'reference_number')) {
                $table->dropColumn('reference_number');
            }
            if (Schema::hasColumn('compliance_submissions', 'review_notes')) {
                $table->dropColumn('review_notes');
            }
        });
    }
};
