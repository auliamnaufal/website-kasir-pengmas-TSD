<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductIndexTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Test',
            'email' => 'admin-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
    }

    private function product(): Product
    {
        $category = Category::create(['category_name' => 'Minuman']);

        return Product::create([
            'product_name' => 'Teh Botol',
            'category_id' => $category->category_id,
            'quantity_in_stock' => 10,
            'harga_beli' => 1000,
            'harga_jual' => 1500,
        ]);
    }

    public function test_product_page_renders(): void
    {
        $this->product();

        $response = $this->actingAs($this->admin())->get(route('products.index'));

        $response->assertOk();
        $response->assertSee('Teh Botol');
    }

    public function test_product_search_returns_barcode_for_each_row(): void
    {
        $product = $this->product();

        $response = $this->actingAs($this->admin())
            ->getJson(route('products.search', ['searchTerm' => 'Teh']));

        $response->assertOk();
        $this->assertEquals($product->product_id, $response->json('0.product_id'));

        $barcode = $response->json('0.barcode');
        $this->assertIsString($barcode);
        $this->assertNotEmpty(base64_decode($barcode, true));
    }
}
