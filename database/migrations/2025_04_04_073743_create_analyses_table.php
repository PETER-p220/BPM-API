<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAnalysesTable extends Migration
{
    public function up()
    {
        Schema::create('analyses', function (Blueprint $table) {
            $table->id('analysis_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('tender_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('serial_number')->nullable();
            $table->text('item_description')->nullable();
            $table->integer('quoted_quantity')->nullable();
            $table->string('quoted_unit')->nullable();
            $table->decimal('quoted_rate', 15, 2)->nullable();
            $table->decimal('quoted_amount', 15, 2)->nullable();
            $table->integer('quantity')->nullable();
            $table->decimal('rate', 15, 2)->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('source')->nullable();
            $table->string('urgent_status')->nullable();
            $table->decimal('total_amount_vat_excl', 15, 2)->nullable();
            $table->decimal('total_amount_vat_incl', 15, 2)->nullable();
            $table->decimal('total_amount_needed', 15, 2)->nullable();
            $table->decimal('site_contingency', 15, 2)->nullable();
            $table->decimal('total_investment', 15, 2)->nullable();
            $table->decimal('projected_profit', 15, 2)->nullable();
            $table->decimal('projected_profit_percentage', 15, 2)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('reason_for_reject')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('analyses');
    }
}