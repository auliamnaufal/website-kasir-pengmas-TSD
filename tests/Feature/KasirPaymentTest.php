<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\JabatanConfig;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KasirPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(int $stock = 10, int $price = 15000): Product
    {
        $category = Category::create(['category_name' => 'Sembako']);

        return Product::create([
            'product_name' => 'Beras 1kg',
            'category_id' => $category->category_id,
            'quantity_in_stock' => $stock,
            'harga_beli' => $price - 1000,
            'harga_jual' => $price,
        ]);
    }

    public function test_cash_payment_is_processed_without_selecting_a_pegawai(): void
    {
        $product = $this->makeProduct();

        $response = $this->postJson(route('kasir.prosespembayaran'), [
            'payment_method' => 'Pembayaran Tunai',
            'products' => [
                ['id' => $product->product_id, 'quantity' => 2, 'price' => 15000],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('payments', [
            'payment_method' => 'cash',
            'payment_amount' => 30000,
        ]);
        $this->assertNull(Order::first()->user_id);
    }

    public function test_qris_payment_is_stored_as_qris_not_credit_card(): void
    {
        $product = $this->makeProduct();

        $response = $this->postJson(route('kasir.prosespembayaran'), [
            'payment_method' => 'Pembayaran QRIS',
            'products' => [
                ['id' => $product->product_id, 'quantity' => 1, 'price' => 15000],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('payments', ['payment_method' => 'qris']);
        $this->assertDatabaseMissing('payments', ['payment_method' => 'credit_card']);
    }

    public function test_potong_gaji_payment_is_rejected_without_a_pegawai(): void
    {
        $product = $this->makeProduct();

        $response = $this->postJson(route('kasir.prosespembayaran'), [
            'payment_method' => 'Pembayaran Potong Gaji',
            'products' => [
                ['id' => $product->product_id, 'quantity' => 1, 'price' => 15000],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('user_id');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_potong_gaji_payment_succeeds_with_a_pegawai_selected(): void
    {
        $product = $this->makeProduct();
        JabatanConfig::create([
            'jabatan' => 'Staff',
            'level' => 1,
            'simpanan_wajib' => 100000,
            'max_potongan_persen' => 50,
        ]);
        $pegawai = User::create([
            'name' => 'Karyawan Satu',
            'email' => 'karyawan1@example.com',
            'password' => 'secret',
            'role' => 'karyawan',
            'jabatan' => 'Staff',
            'gaji' => 1000000,
        ]);

        $response = $this->postJson(route('kasir.prosespembayaran'), [
            'payment_method' => 'Pembayaran Potong Gaji',
            'user_id' => $pegawai->user_id,
            'products' => [
                ['id' => $product->product_id, 'quantity' => 1, 'price' => 15000],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('payments', ['payment_method' => 'potong_gaji']);
        $this->assertSame($pegawai->user_id, Order::first()->user_id);
    }
}
