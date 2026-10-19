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
        // Generic ledger shared by kasir (pembelian) and future P2/P3 modules
        // (pinjaman/kredit) — see issue #3. P1 only ever writes source_type
        // 'pembelian'; source_id is not a strict FK since it points at
        // different tables depending on source_type.
        Schema::create('piutang', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->enum('source_type', ['pembelian', 'pinjaman', 'kredit']);
            $table->unsignedBigInteger('source_id');
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['belum_lunas', 'lunas'])->default('belum_lunas');
            $table->string('period'); // "YYYY-MM", cutoff 16th-prev -> 15th-this, see Piutang::periodFor()
            $table->timestamp('settled_at')->nullable();
            $table->unsignedBigInteger('settled_by')->nullable();
            $table->foreign('settled_by')->references('user_id')->on('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('piutang');
    }
};
