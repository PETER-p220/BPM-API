<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Add HR-specific fields to attendances table
            $table->date('meeting_date')->nullable()->after('attenda_id');
            $table->string('meeting_type')->nullable()->after('meeting_date');
            $table->string('location')->nullable()->after('meeting_type');
            $table->text('attendees')->nullable()->after('location');
            $table->text('notes')->nullable()->after('attendees');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Remove HR-specific fields from attendances table
            $table->dropColumn(['meeting_date', 'meeting_type', 'location', 'attendees', 'notes']);
        });
    }
};
