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
        // Satu baris per bulan cicilan. Nilainya diisi dari hasil mesin angsuran
        // P2; tabel ini hanya menyimpan jadwal dan status bayarnya.
        Schema::create('kredit_angsuran', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kredit_barang_id');
            $table->foreign('kredit_barang_id')->references('id')->on('kredit_barang')->onDelete('cascade');
            $table->unsignedTinyInteger('bulan_ke');
            $table->date('jatuh_tempo');
            $table->decimal('pokok', 15, 2);
            $table->decimal('bunga', 15, 2);
            $table->enum('status', ['belum_lunas', 'lunas'])->default('belum_lunas');
            $table->timestamp('dibayar_pada')->nullable();
            $table->string('kode_perkiraan')->nullable(); // slot Konsultan 2027, tanpa logic
            $table->timestamps();

            $table->unique(['kredit_barang_id', 'bulan_ke']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kredit_angsuran');
    }
};
