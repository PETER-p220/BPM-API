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
        Schema::table('invoices', function (Blueprint $table) {
            // Add company/issuer fields
            $table->string('company_name')->nullable()->after('status');
            $table->string('company_email')->nullable()->after('company_name');
            $table->string('company_phone')->nullable()->after('company_email');
            $table->string('company_website')->nullable()->after('company_phone');
            $table->string('company_tin')->nullable()->after('company_website');
            $table->string('company_vrn')->nullable()->after('company_tin');
            $table->text('company_address')->nullable()->after('company_vrn');
            $table->string('company_logo')->nullable()->after('company_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'company_name',
                'company_email',
                'company_phone',
                'company_website',
                'company_tin',
                'company_vrn',
                'company_address',
                'company_logo'
            ]);
        });
    }
};
