<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->string('procurement_method')->nullable()->after('tender_number');
            $table->string('submission_mode')->nullable()->after('procurement_method');
            $table->string('bid_currency', 10)->nullable()->after('submission_mode');
            $table->decimal('estimated_value', 15, 2)->nullable()->after('bid_currency');
            $table->decimal('tender_fee', 15, 2)->nullable()->after('estimated_value');
            $table->boolean('bid_security_required')->default(false)->after('tender_fee');
            $table->decimal('bid_security_amount', 15, 2)->nullable()->after('bid_security_required');
            $table->date('site_visit_date')->nullable()->after('bid_security_amount');
            $table->date('clarification_deadline')->nullable()->after('site_visit_date');
            $table->string('contract_duration')->nullable()->after('clarification_deadline');
            $table->text('scope_summary')->nullable()->after('contract_duration');
            $table->text('eligibility_criteria')->nullable()->after('scope_summary');
        });
    }

    public function down(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->dropColumn([
                'procurement_method',
                'submission_mode',
                'bid_currency',
                'estimated_value',
                'tender_fee',
                'bid_security_required',
                'bid_security_amount',
                'site_visit_date',
                'clarification_deadline',
                'contract_duration',
                'scope_summary',
                'eligibility_criteria',
            ]);
        });
    }
};
