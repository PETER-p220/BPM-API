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
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('salary', 15, 2)->nullable()->after('email');
            $table->date('hire_date')->nullable()->after('salary');
            $table->text('address')->nullable()->after('hire_date');
            $table->string('emergency_contact')->nullable()->after('address');
            $table->string('emergency_phone')->nullable()->after('emergency_contact');
            $table->date('birth_date')->nullable()->after('emergency_phone');
            $table->string('gender')->nullable()->after('birth_date');
            $table->string('national_id')->nullable()->after('gender');
            $table->string('bank_account')->nullable()->after('national_id');
            $table->string('bank_name')->nullable()->after('bank_account');
            $table->text('notes')->nullable()->after('bank_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'salary',
                'hire_date',
                'address',
                'emergency_contact',
                'emergency_phone',
                'birth_date',
                'gender',
                'national_id',
                'bank_account',
                'bank_name',
                'notes'
            ]);
        });
    }
};
