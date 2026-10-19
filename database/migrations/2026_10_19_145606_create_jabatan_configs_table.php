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
        Schema::create('jabatan_configs', function (Blueprint $table) {
            $table->id();
            $table->string('jabatan')->unique();
            $table->unsignedInteger('level');
            $table->decimal('simpanan_wajib', 12, 2);
            $table->decimal('max_potongan_persen', 5, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jabatan_configs');
    }
};
