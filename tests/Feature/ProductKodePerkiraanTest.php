<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductKodePerkiraanTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        return User::create([
            'name' => 'Admin Test',
            'email' => 'admin-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
    }

    private function category(): Category
    {
        return Category::create(['category_name' => 'Minuman']);
    }

    public function test_product_can_be_created_with_kode_perkiraan(): void
    {
        $category = $this->category();

        $response = $this->actingAs($this->actingUser())->post(route('products.store'), [
            'product_name' => 'Teh Botol',
            'category_id' => $category->category_id,
            'quantity_in_stock' => 10,
            'harga_beli' => 1000,
            'harga_jual' => 1500,
            'kode_perkiraan' => 'AKUN-001',
        ]);

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', [
            'product_name' => 'Teh Botol',
            'kode_perkiraan' => 'AKUN-001',
        ]);
    }

    public function test_product_can_be_created_without_kode_perkiraan(): void
    {
        $category = $this->category();

        $response = $this->actingAs($this->actingUser())->post(route('products.store'), [
            'product_name' => 'Kopi Sachet',
            'category_id' => $category->category_id,
            'quantity_in_stock' => 5,
            'harga_beli' => 500,
            'harga_jual' => 800,
        ]);

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', [
            'product_name' => 'Kopi Sachet',
            'kode_perkiraan' => null,
        ]);
    }

    public function test_product_can_be_updated_with_kode_perkiraan(): void
    {
        $category = $this->category();
        $product = Product::create([
            'product_name' => 'Gula Pasir',
            'category_id' => $category->category_id,
            'quantity_in_stock' => 20,
            'harga_beli' => 2000,
            'harga_jual' => 2500,
        ]);

        $response = $this->actingAs($this->actingUser())->put(route('products.update', $product->product_id), [
            'product_name' => 'Gula Pasir',
            'category_id' => $category->category_id,
            'quantity_in_stock' => 20,
            'harga_beli' => 2000,
            'harga_jual' => 2500,
            'kode_perkiraan' => 'AKUN-002',
        ]);

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', [
            'product_id' => $product->product_id,
            'kode_perkiraan' => 'AKUN-002',
        ]);
    }

    public function test_product_can_be_cleared_of_kode_perkiraan(): void
    {
        $category = $this->category();
        $product = Product::create([
            'product_name' => 'Minyak Goreng',
            'category_id' => $category->category_id,
            'quantity_in_stock' => 15,
            'harga_beli' => 3000,
            'harga_jual' => 3500,
            'kode_perkiraan' => 'AKUN-003',
        ]);

        $response = $this->actingAs($this->actingUser())->put(route('products.update', $product->product_id), [
            'product_name' => 'Minyak Goreng',
            'category_id' => $category->category_id,
            'quantity_in_stock' => 15,
            'harga_beli' => 3000,
            'harga_jual' => 3500,
            'kode_perkiraan' => '',
        ]);

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', [
            'product_id' => $product->product_id,
            'kode_perkiraan' => null,
        ]);
    }
}
