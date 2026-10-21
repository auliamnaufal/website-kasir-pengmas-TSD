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
        // Buku simpanan anggota: satu baris per setoran. Saldo = SUM(jumlah) per jenis.
        Schema::create('simpanan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->enum('jenis', ['wajib', 'sukarela']);
            $table->decimal('jumlah', 15, 2);
            $table->date('tanggal');
            $table->string('periode'); // "YYYY-MM"; simpanan wajib maksimal satu per periode
            // Asal setoran: null = setoran manual, 'pinjaman' = bagian administrasi pinjaman.
            $table->string('sumber_type')->nullable();
            $table->unsignedBigInteger('sumber_id')->nullable();
            $table->string('keterangan')->nullable();
            $table->unsignedBigInteger('dibuat_oleh')->nullable();
            $table->foreign('dibuat_oleh')->references('user_id')->on('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['user_id', 'jenis', 'periode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpanan');
    }
};
