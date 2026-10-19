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
            $table->string('employee_id')->nullable()->unique()->after('user_id');
            $table->string('nik')->nullable()->unique()->after('name');
            $table->string('jabatan')->nullable()->after('role');
            $table->decimal('gaji', 12, 2)->nullable()->after('jabatan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['employee_id', 'nik', 'jabatan', 'gaji']);
        });
    }
};
