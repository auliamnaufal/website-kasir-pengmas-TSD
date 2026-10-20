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
        Schema::create('kredit_barang', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->string('nama_barang');
            $table->decimal('pokok', 15, 2);
            $table->unsignedTinyInteger('tenor'); // bulan
            $table->decimal('administrasi', 15, 2);
            $table->decimal('bunga_persen', 5, 2);
            $table->date('tanggal_pengajuan'); // dasar hitung plafon tahunan
            $table->date('tanggal_mulai')->nullable(); // diisi saat jadwal angsuran dibuat
            // ditolak = auto-reject (plafon terlampaui), tidak memakai plafon.
            $table->enum('status', ['disetujui', 'ditolak', 'lunas'])->default('disetujui');
            $table->string('alasan_penolakan')->nullable();
            $table->string('kode_perkiraan')->nullable(); // slot Konsultan 2027, tanpa logic
            $table->timestamps();

            $table->index(['user_id', 'tanggal_pengajuan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kredit_barang');
    }
};
