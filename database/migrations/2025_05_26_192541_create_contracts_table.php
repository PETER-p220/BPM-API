<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContractsTable extends Migration
{
    public function up()
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id('contract_id');
            $table->string('title');
            $table->string('time_line_category');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('pdf_file')->nullable();
            $table->enum('status', ['on-progress', 'cancelled', 'ended'])->default('on-progress');
            $table->foreignId('user_id');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('contracts');
    }
}