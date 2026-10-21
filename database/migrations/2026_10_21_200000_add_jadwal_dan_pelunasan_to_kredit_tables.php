<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Melengkapi tabel kredit dari migration 2026_10_20 dengan parameter jadwal
     * yang dibekukan saat pengajuan (supaya jadwal bisa dihitung ulang persis
     * oleh MesinAngsuran P2) dan kolom pelunasan. Dibuat sebagai migration baru
     * agar database yang sudah menjalankan migration lama tetap aman.
     */
    public function up(): void
    {
        Schema::table('kredit_barang', function (Blueprint $table) {
            $table->enum('metode_bayar', ['potong_gaji', 'bayar_sendiri'])->default('bayar_sendiri')->after('tenor');
            $table->string('metode_bunga', 10)->default('flat')->after('bunga_persen');
            // Bunga hanya dikenakan sampai bulan ini (null = sepanjang tenor).
            $table->unsignedTinyInteger('bunga_sampai_bulan')->nullable()->after('metode_bunga');
            // Pelunasan dipercepat tetap ditagih bunga sampai bulan ini.
            $table->unsignedTinyInteger('bulan_bunga_minimum')->default(0)->after('bunga_sampai_bulan');
            $table->decimal('pelunasan_pokok', 15, 2)->nullable()->after('alasan_penolakan');
            $table->decimal('pelunasan_bunga', 15, 2)->nullable()->after('pelunasan_pokok');
            $table->timestamp('dilunasi_pada')->nullable()->after('pelunasan_bunga');
            $table->unsignedBigInteger('dibuat_oleh')->nullable()->after('dilunasi_pada');
        });

        Schema::table('kredit_angsuran', function (Blueprint $table) {
            // Sisa terjadwal SETELAH angsuran bulan itu dibayar (sama dengan pinjaman_angsuran P2).
            $table->decimal('sisa_pokok', 15, 2)->default(0)->after('bunga');
            $table->decimal('sisa_bunga', 15, 2)->default(0)->after('sisa_pokok');
            $table->unsignedBigInteger('dibayar_oleh')->nullable()->after('dibayar_pada');
        });

        // `pelunasan` = angsuran yang ditutup pelunasan dipercepat, bukan dibayar bulanan.
        Schema::table('kredit_angsuran', function (Blueprint $table) {
            $table->enum('status', ['belum_lunas', 'lunas', 'pelunasan'])->default('belum_lunas')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kredit_angsuran', function (Blueprint $table) {
            $table->enum('status', ['belum_lunas', 'lunas'])->default('belum_lunas')->change();
            $table->dropColumn(['sisa_pokok', 'sisa_bunga', 'dibayar_oleh']);
        });

        Schema::table('kredit_barang', function (Blueprint $table) {
            $table->dropColumn([
                'metode_bayar', 'metode_bunga', 'bunga_sampai_bulan', 'bulan_bunga_minimum',
                'pelunasan_pokok', 'pelunasan_bunga', 'dilunasi_pada', 'dibuat_oleh',
            ]);
        });
    }
};
