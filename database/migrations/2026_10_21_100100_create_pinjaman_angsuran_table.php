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
        // Satu baris per bulan, disalin dari hasil MesinAngsuran saat pinjaman dibuat.
        Schema::create('pinjaman_angsuran', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pinjaman_id');
            $table->foreign('pinjaman_id')->references('id')->on('pinjaman')->onDelete('cascade');
            $table->unsignedTinyInteger('bulan_ke');
            $table->date('jatuh_tempo');
            $table->decimal('pokok', 15, 2);
            $table->decimal('bunga', 15, 2);
            $table->decimal('sisa_pokok', 15, 2); // setelah angsuran ini dibayar
            $table->decimal('sisa_bunga', 15, 2); // bunga terjadwal yang tersisa setelah angsuran ini
            // pelunasan = ditutup oleh pelunasan dipercepat (pokoknya ikut dibayar di situ).
            $table->enum('status', ['belum_lunas', 'lunas', 'pelunasan'])->default('belum_lunas');
            $table->timestamp('dibayar_pada')->nullable();
            $table->unsignedBigInteger('dibayar_oleh')->nullable();
            $table->foreign('dibayar_oleh')->references('user_id')->on('users')->onDelete('set null');
            $table->string('kode_perkiraan')->nullable(); // slot Konsultan 2027, tanpa logic
            $table->timestamps();

            $table->unique(['pinjaman_id', 'bulan_ke']);
            $table->index('jatuh_tempo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pinjaman_angsuran');
    }
};
