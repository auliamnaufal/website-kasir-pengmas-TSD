<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\JabatanConfig;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrukKasirTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(int $price = 15000): Product
    {
        $category = Category::create(['category_name' => 'Sembako']);

        return Product::create([
            'product_name' => 'Beras 1kg',
            'category_id' => $category->category_id,
            'quantity_in_stock' => 10,
            'harga_beli' => $price - 1000,
            'harga_jual' => $price,
        ]);
    }

    public function test_receipt_html_is_rendered_server_side_with_brand_and_totals(): void
    {
        $product = $this->makeProduct();

        $response = $this->postJson(route('kasir.prosespembayaran'), [
            'payment_method' => 'Pembayaran Tunai',
            'products' => [
                ['id' => $product->product_id, 'quantity' => 2, 'price' => 15000],
            ],
        ]);

        $response->assertOk();

        $html = $response->json('receipt.html');
        $this->assertIsString($html);

        $this->assertStringContainsString(config('kasir.nama_toko'), $html);
        $this->assertStringContainsString('Struk Pembayaran', $html);
        $this->assertStringContainsString('Beras 1kg', $html);
        $this->assertStringContainsString('&times;', $html);
        $this->assertStringContainsString('15.000', $html);
        $this->assertStringContainsString('Rp 30.000', $html);
        $this->assertStringContainsString('Tunai', $html);
        $this->assertStringContainsString('size: 80mm auto', $html);
        $this->assertStringContainsString('class="barcode"', $html);
        $this->assertStringNotContainsString('Potong gaji', $html);
    }

    public function test_potong_gaji_receipt_names_the_employee(): void
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

        $html = $response->json('receipt.html');
        $this->assertStringContainsString('Potong Gaji', $html);
        $this->assertStringContainsString('Karyawan Satu', $html);
        $this->assertStringContainsString('Rp 15.000', $html);
    }
}
