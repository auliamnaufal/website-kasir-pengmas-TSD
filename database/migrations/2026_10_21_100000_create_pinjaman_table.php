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
        Schema::create('pinjaman', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->decimal('pokok', 15, 2);
            $table->unsignedTinyInteger('tenor'); // bulan
            $table->decimal('bunga_persen', 5, 2);
            $table->string('metode_bunga'); // flat | efektif, lihat MesinAngsuran
            // Dibekukan saat pengajuan supaya perubahan config tidak mengubah pinjaman lama.
            $table->unsignedTinyInteger('bulan_bunga_minimum');
            // Administrasi 1x per pinjaman, dipecah: pendapatan koperasi + simpanan sukarela anggota.
            $table->decimal('administrasi', 15, 2);
            $table->decimal('administrasi_pendapatan', 15, 2);
            $table->decimal('administrasi_sukarela', 15, 2);
            // Pelunasan pinjaman lama yang dipotong dari pinjaman baru ini (top-up).
            $table->decimal('kompensasi', 15, 2)->default(0);
            $table->decimal('dana_diterima', 15, 2); // pokok - administrasi - kompensasi
            $table->enum('metode_bayar', ['potong_gaji', 'bayar_sendiri']);
            $table->date('tanggal_pengajuan');
            $table->date('tanggal_mulai')->nullable(); // angsuran ke-1 jatuh tempo 1 bulan setelahnya
            // ditolak = auto-reject sistem (plafon / kecukupan gaji), tidak punya jadwal angsuran.
            $table->enum('status', ['aktif', 'ditolak', 'lunas'])->default('aktif');
            $table->string('alasan_penolakan')->nullable();
            // Total yang dibayar sampai lunas, pokok dan bunga dipisah.
            $table->decimal('pelunasan_pokok', 15, 2)->nullable();
            $table->decimal('pelunasan_bunga', 15, 2)->nullable();
            $table->timestamp('dilunasi_pada')->nullable();
            // Diisi bila lunas karena dikompensasi pinjaman baru.
            $table->unsignedBigInteger('dikompensasi_oleh')->nullable();
            $table->foreign('dikompensasi_oleh')->references('id')->on('pinjaman')->onDelete('set null');
            $table->unsignedBigInteger('dibuat_oleh')->nullable();
            $table->foreign('dibuat_oleh')->references('user_id')->on('users')->onDelete('set null');
            $table->string('kode_perkiraan')->nullable(); // slot Konsultan 2027, tanpa logic
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pinjaman');
    }
};
