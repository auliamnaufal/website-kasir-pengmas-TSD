<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Old enum values are dropped entirely per spec (no mapping needed, no
        // production data exists yet) — drop + re-add instead of ->change()
        // to avoid a doctrine/dbal dependency.
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->enum('payment_method', ['cash', 'qris', 'potong_gaji'])->after('payment_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->enum('payment_method', ['credit_card', 'cash', 'bank_transfer'])->after('payment_amount');
        });
    }
};
