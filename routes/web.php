<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\KaryawanController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\KasirController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\DashboardController;

Route::get('/', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/', [AuthController::class, 'login'])->name('login.submit');
Route::post('logout', [AuthController::class, 'logout'])->name('logout');


// Route::get('/dashboard/laporan', [DashboardController::class, 'index']);
// Route::get('/dashboard/laporan-bulanan', [DashboardController::class, 'laporanBulanan']);
// Route::get('/dashboard/produk-laporan', [DashboardController::class, 'produkLaporan']);





Route::get('/search-products', [KasirController::class, 'search'])->name('kasir.search');
Route::post('/kasir/proses-pembayaran', [KasirController::class, 'prosesPembayaran'])->name('kasir.prosespembayaran');
Route::post('/kasir/cetak-struk', [KasirController::class, 'cetakStruk'])->name('kasir.cetakstruk');

Route::middleware(['auth'])->group(function () {

    Route::middleware(['role:admin'])->prefix('admin')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('/laporan-bulanan', [DashboardController::class, 'monthlyReport'])->name('admin.monthlyreport');
        Route::get('/karyawan', [KaryawanController::class, 'index'])->name('karyawan.index');
        Route::get('/karyawan/create', [KaryawanController::class, 'create'])->name('karyawan.create');
        Route::post('/karyawan', [KaryawanController::class, 'store'])->name('karyawan.store');
        Route::delete('/karyawan/{email}', [KaryawanController::class, 'destroy'])->name('karyawan.destroy');
        Route::get('/produk-terlaris', [DashboardController::class, 'getProdukTerlaris'])->name('dashboard.produkTerlaris');
    });

    Route::get('/kasir', function () {
        return view('contents.kasir');
    })->name('kasir.index');

    Route::prefix('kelola')->group(function () {

        // Manajemen produk
        Route::get('/produk', [ProductController::class, 'index'])->name('products.index');
        Route::post('/produk', [ProductController::class, 'store'])->name('products.store');
        Route::put('/produk/{id}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/produk/{id}', [ProductController::class, 'destroy'])->name('products.destroy');
        Route::post('/produk/category', [ProductController::class, 'storeCategory'])->name('products.category.store');
        Route::get('/produk/search', [ProductController::class, 'search'])->name('products.search');
        Route::post('/produk/{id}/update-stock', [ProductController::class, 'updateStock'])->name('products.updateStock');
        Route::post('/produk/print-barcode', [ProductController::class, 'printBarcode'])->name('products.printBarcode');
        Route::delete('/produk/category/{category_id}', [ProductController::class, 'destroyCategory'])->name('products.category.destroy');
    });
});
