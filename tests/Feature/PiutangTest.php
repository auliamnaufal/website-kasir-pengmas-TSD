<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\JabatanConfig;
use App\Models\Order;
use App\Models\Piutang;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PiutangTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(int $price): Product
    {
        $category = Category::create(['category_name' => 'Sembako']);

        return Product::create([
            'product_name' => 'Beras 1kg',
            'category_id' => $category->category_id,
            'quantity_in_stock' => 100,
            'harga_beli' => $price - 1000,
            'harga_jual' => $price,
        ]);
    }

    private function makePegawai(?string $jabatan = 'Staff', ?float $gaji = 1000000): User
    {
        return User::create([
            'name' => 'Karyawan',
            'email' => 'karyawan+'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => 'karyawan',
            'jabatan' => $jabatan,
            'gaji' => $gaji,
        ]);
    }

    private function makeJabatanConfig(string $jabatan = 'Staff', float $maxPotonganPersen = 50): JabatanConfig
    {
        return JabatanConfig::create([
            'jabatan' => $jabatan,
            'level' => 1,
            'simpanan_wajib' => 100000,
            'max_potongan_persen' => $maxPotonganPersen,
        ]);
    }

    private function bayarPotongGaji(User $pegawai, int $price, int $qty = 1): \Illuminate\Testing\TestResponse
    {
        $product = $this->makeProduct($price);

        return $this->postJson(route('kasir.prosespembayaran'), [
            'payment_method' => 'Pembayaran Potong Gaji',
            'user_id' => $pegawai->user_id,
            'products' => [
                ['id' => $product->product_id, 'quantity' => $qty, 'price' => $price],
            ],
        ]);
    }

    public function test_potong_gaji_under_the_limit_succeeds_and_records_piutang(): void
    {
        $this->makeJabatanConfig('Staff', 50); // limit = 1,000,000 * 50% = 500,000
        $pegawai = $this->makePegawai('Staff', 1000000);

        $response = $this->bayarPotongGaji($pegawai, 100000);

        $response->assertOk();
        $this->assertDatabaseHas('piutang', [
            'user_id' => $pegawai->user_id,
            'source_type' => 'pembelian',
            'source_id' => \App\Models\Payment::first()->payment_id,
            'amount' => 100000,
            'status' => 'belum_lunas',
            'period' => Piutang::periodFor(now()),
        ]);
    }

    public function test_potong_gaji_exactly_at_the_limit_succeeds(): void
    {
        $this->makeJabatanConfig('Staff', 50); // limit = 500,000
        $pegawai = $this->makePegawai('Staff', 1000000);

        Piutang::create([
            'user_id' => $pegawai->user_id,
            'source_type' => 'pembelian',
            'source_id' => 1,
            'amount' => 400000,
            'status' => 'belum_lunas',
            'period' => Piutang::periodFor(now()),
        ]);

        $response = $this->bayarPotongGaji($pegawai, 100000); // 400,000 + 100,000 == 500,000

        $response->assertOk();
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_potong_gaji_exceeding_the_limit_is_rejected_and_nothing_is_created(): void
    {
        $this->makeJabatanConfig('Staff', 50); // limit = 500,000
        $pegawai = $this->makePegawai('Staff', 1000000);

        Piutang::create([
            'user_id' => $pegawai->user_id,
            'source_type' => 'pembelian',
            'source_id' => 1,
            'amount' => 450000,
            'status' => 'belum_lunas',
            'period' => Piutang::periodFor(now()),
        ]);

        $response = $this->bayarPotongGaji($pegawai, 100000); // 450,000 + 100,000 > 500,000

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('payment_method');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('piutang', 1); // only the pre-existing row
    }

    public function test_lunas_piutang_does_not_count_toward_the_limit(): void
    {
        $this->makeJabatanConfig('Staff', 50); // limit = 500,000
        $pegawai = $this->makePegawai('Staff', 1000000);

        Piutang::create([
            'user_id' => $pegawai->user_id,
            'source_type' => 'pembelian',
            'source_id' => 1,
            'amount' => 450000,
            'status' => 'lunas',
            'period' => Piutang::periodFor(now()),
        ]);

        $response = $this->bayarPotongGaji($pegawai, 100000);

        $response->assertOk();
    }

    public function test_piutang_from_a_different_period_does_not_count_toward_the_limit(): void
    {
        $this->makeJabatanConfig('Staff', 50); // limit = 500,000
        $pegawai = $this->makePegawai('Staff', 1000000);

        Piutang::create([
            'user_id' => $pegawai->user_id,
            'source_type' => 'pembelian',
            'source_id' => 1,
            'amount' => 450000,
            'status' => 'belum_lunas',
            'period' => '2000-01', // long-closed period
        ]);

        $response = $this->bayarPotongGaji($pegawai, 100000);

        $response->assertOk();
    }

    public function test_potong_gaji_counts_piutang_from_other_source_types_toward_the_limit(): void
    {
        $this->makeJabatanConfig('Staff', 50); // limit = 500,000
        $pegawai = $this->makePegawai('Staff', 1000000);

        Piutang::create([
            'user_id' => $pegawai->user_id,
            'source_type' => 'pinjaman',
            'source_id' => 1,
            'amount' => 450000,
            'status' => 'belum_lunas',
            'period' => Piutang::periodFor(now()),
        ]);

        $response = $this->bayarPotongGaji($pegawai, 100000); // 450,000 + 100,000 > 500,000

        $response->assertStatus(422);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_pegawai_without_gaji_or_jabatan_is_not_blocked(): void
    {
        // Spec #3 story 21: accounts predating this feature (nullable gaji/jabatan)
        // keep working — there's no limit to enforce without both.
        $pegawai = $this->makePegawai(null, null);

        $response = $this->bayarPotongGaji($pegawai, 999999999);

        $response->assertOk();
    }

    public function test_period_for_a_transaction_on_the_15th_is_the_same_month(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00');

        $this->makeJabatanConfig('Staff', 50);
        $pegawai = $this->makePegawai('Staff', 1000000);

        $this->bayarPotongGaji($pegawai, 100000)->assertOk();

        $this->assertDatabaseHas('piutang', ['period' => '2026-10']);

        Carbon::setTestNow();
    }

    public function test_period_for_a_transaction_on_the_16th_rolls_over_to_next_month(): void
    {
        Carbon::setTestNow('2026-10-16 10:00:00');

        $this->makeJabatanConfig('Staff', 50);
        $pegawai = $this->makePegawai('Staff', 1000000);

        $this->bayarPotongGaji($pegawai, 100000)->assertOk();

        $this->assertDatabaseHas('piutang', ['period' => '2026-11']);

        Carbon::setTestNow();
    }
}
