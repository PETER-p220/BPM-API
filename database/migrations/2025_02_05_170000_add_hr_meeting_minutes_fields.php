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
        Schema::table('meeting_minutes', function (Blueprint $table) {
            // Add HR meeting minutes fields
            $table->string('meeting_title')->nullable();
            $table->date('meeting_date')->nullable();
            $table->text('attendees')->nullable();
            $table->text('agenda')->nullable();
            $table->text('discussion')->nullable();
            $table->text('decisions')->nullable();
            $table->string('next_meeting')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meeting_minutes', function (Blueprint $table) {
            // Remove HR meeting minutes fields
            $table->dropColumn([
                'meeting_title',
                'meeting_date', 
                'attendees',
                'agenda',
                'discussion',
                'decisions',
                'next_meeting'
            ]);
        });
    }
};
