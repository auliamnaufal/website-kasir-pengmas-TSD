<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\JabatanConfig;
use App\Models\Piutang;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PiutangTandaiLunasTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'User',
            'email' => 'user'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => 'karyawan',
        ], $overrides));
    }

    private function piutang(User $pegawai, array $overrides = []): Piutang
    {
        return Piutang::create(array_merge([
            'user_id' => $pegawai->user_id,
            'source_type' => 'pembelian',
            'source_id' => 1,
            'amount' => 100000,
            'status' => 'belum_lunas',
            'period' => Piutang::periodFor(now()),
        ], $overrides));
    }

    public function test_admin_can_mark_a_piutang_as_lunas(): void
    {
        $admin = $this->user(['role' => 'admin']);
        $pegawai = $this->user();
        $piutang = $this->piutang($pegawai);

        $response = $this->actingAs($admin)->put(route('piutang.tandai-lunas', $piutang));

        $response->assertRedirect();
        $piutang->refresh();
        $this->assertSame('lunas', $piutang->status);
        $this->assertNotNull($piutang->settled_at);
        $this->assertSame($admin->user_id, $piutang->settled_by);
    }

    public function test_non_admin_is_denied(): void
    {
        $karyawan = $this->user();
        $pegawai = $this->user();
        $piutang = $this->piutang($pegawai);

        $response = $this->actingAs($karyawan)->put(route('piutang.tandai-lunas', $piutang));

        $response->assertRedirect();
        $this->assertDatabaseHas('piutang', ['id' => $piutang->id, 'status' => 'belum_lunas']);
    }

    public function test_marking_an_already_lunas_piutang_is_rejected(): void
    {
        // Decision: reject (422) rather than silently re-settle — settled_at/settled_by
        // is an audit trail of the FIRST settlement (spec #3 story 18) and should not
        // be overwritten by a later click.
        $admin = $this->user(['role' => 'admin']);
        $pegawai = $this->user();
        $piutang = $this->piutang($pegawai, [
            'status' => 'lunas',
            'settled_at' => '2026-01-01 00:00:00',
            'settled_by' => $admin->user_id,
        ]);

        $response = $this->actingAs($admin)->put(route('piutang.tandai-lunas', $piutang));

        $response->assertSessionHasErrors('status');
        $piutang->refresh();
        $this->assertSame('2026-01-01 00:00:00', $piutang->settled_at->format('Y-m-d H:i:s'));
    }

    public function test_once_lunas_a_piutang_is_excluded_from_the_kecukupan_gaji_limit_end_to_end(): void
    {
        JabatanConfig::create(['jabatan' => 'Staff', 'level' => 1, 'simpanan_wajib' => 100000, 'max_potongan_persen' => 50]);
        $admin = $this->user(['role' => 'admin']);
        $pegawai = $this->user(['jabatan' => 'Staff', 'gaji' => 1000000]); // limit = 500,000

        $existing = $this->piutang($pegawai, ['amount' => 450000]);

        $category = Category::create(['category_name' => 'Sembako']);
        $product = Product::create([
            'product_name' => 'Beras 1kg',
            'category_id' => $category->category_id,
            'quantity_in_stock' => 100,
            'harga_beli' => 9000,
            'harga_jual' => 100000,
        ]);

        $attempt = fn () => $this->postJson(route('kasir.prosespembayaran'), [
            'payment_method' => 'Pembayaran Potong Gaji',
            'user_id' => $pegawai->user_id,
            'products' => [
                ['id' => $product->product_id, 'quantity' => 1, 'price' => 100000],
            ],
        ]);

        // Blocked while the existing piutang is still belum_lunas: 450,000 + 100,000 > 500,000.
        $attempt()->assertStatus(422);
        $this->assertDatabaseCount('payments', 0);

        $this->actingAs($admin)->put(route('piutang.tandai-lunas', $existing))->assertRedirect();

        // Now excluded from the sum: 0 + 100,000 <= 500,000.
        $attempt()->assertOk();
        $this->assertDatabaseCount('payments', 1);
    }
}
