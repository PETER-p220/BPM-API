<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::create('tender_reports', function (Blueprint $table) {
        $table->id();

        // Match users.user_id → int (usually signed, but Laravel often uses unsigned)
        $table->unsignedInteger('user_id');           // ← change to unsignedInteger (matches int unsigned)

        // Match tenders.tender_id → bigint (assuming it's unsigned in practice)
        $table->unsignedBigInteger('tender_id');      // ← keep or change to bigInteger() if signed

        $table->enum('report_type', ['technical', 'financial', 'compliance', 'documentation', 'other']);
        $table->text('reason');
        $table->text('recommendations')->nullable();
        $table->string('supporting_document')->nullable();
        $table->string('reported_by');

        $table->timestamps();

        // Foreign keys – now types should match
        $table->foreign('tender_id')
              ->references('tender_id')
              ->on('tenders')
              ->onDelete('cascade');

        $table->foreign('user_id')
              ->references('user_id')
              ->on('users')
              ->onDelete('cascade');
    });
}

    public function down(): void
    {
        Schema::dropIfExists('tender_reports');
    }
};