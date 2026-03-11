<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('automated_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->string('schedule'); // daily, weekly, monthly, hourly
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_run')->nullable();
            $table->timestamp('next_run')->nullable();
            $table->string('last_result')->nullable(); // success, failed, error
            $table->json('parameters')->nullable();
            $table->timestamps();

            $table->index(['enabled']);
            $table->index(['next_run']);
            $table->unique(['name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('automated_tasks');
    }
};
