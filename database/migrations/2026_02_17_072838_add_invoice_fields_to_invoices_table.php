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
            // Add missing invoice fields
            $table->string('invoice_number')->unique()->after('id');
            $table->string('client_name')->after('invoice_number');
            $table->string('client_email')->after('client_name');
            $table->string('client_phone')->after('client_email');
            $table->string('item_description')->after('client_phone');
            $table->integer('number_of_cars')->nullable()->after('item_description');
            $table->integer('period_months')->nullable()->after('number_of_cars');
            $table->string('uom')->nullable()->after('period_months');
            $table->decimal('unit_price', 15, 2)->nullable()->after('uom');
            $table->decimal('gross_value', 15, 2)->nullable()->after('unit_price');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('gross_value');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('tax_rate');
            $table->decimal('total_amount', 15, 2)->nullable()->after('tax_amount');
            $table->date('invoice_date')->nullable()->after('total_amount');
            $table->date('due_date')->nullable()->after('invoice_date');
            $table->text('notes')->nullable()->after('due_date');
            $table->string('status')->default('draft')->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Drop the added columns
            $table->dropColumn([
                'invoice_number',
                'client_name',
                'client_email',
                'client_phone',
                'item_description',
                'number_of_cars',
                'period_months',
                'uom',
                'unit_price',
                'gross_value',
                'tax_rate',
                'tax_amount',
                'total_amount',
                'invoice_date',
                'due_date',
                'notes',
                'status'
            ]);
        });
    }
};
