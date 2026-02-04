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
    Schema::table('tender_doc_submissions', function (Blueprint $table) {
        $table->string('qualifications')->nullable()->change();
    });
}

public function down(): void
{
    Schema::table('tender_doc_submissions', function (Blueprint $table) {
        $table->string('qualifications')->nullable(false)->change();
    });
    }
};
