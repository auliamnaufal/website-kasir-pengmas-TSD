@extends('layouts.app')

@section('content')

<!-- Alert Sukses -->
@if (session('success'))
    <div class="alert alert-success" id="success-message">
        {{ session('success') }}
    </div>
@endif

<!-- Alert Gagal -->
@if ($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<!-- Tabel Kategori Produk -->
<!-- Tabel Kategori Produk -->
<div class="card mb-4">
    <div class="card-body">
        <!-- Header Card -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="text-primary fw-bold">Kategori Produk</h3>
            <button type="button" class="btn btn-success btn-icon-text text-white" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="mdi mdi-loupe btn-icon-prepend"></i>Tambah Kategori
            </button>
        </div>

        <!-- Tabel Kategori -->
        <!-- Tabel Kategori -->
        <div class="table-responsive scrollable-table-container mt-2">
            <table class="table table-hover table-striped">
                <thead style="background-color: #e8daef; color: #6c3483;">
                    <tr>
                        {{-- <th>ID Kategori</th> --}}
                        <th>Nama Kategori</th>
                        <th>Jumlah Jenis Produk</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($categories as $category)
                    <tr>
                        {{-- <td>{{ $category->category_id }}</td> --}}
                        <td>{{ $category->category_name }}</td>
                        <td>{{ $category->products->count() }}</td>
                        <td>
                            <!-- Tombol Lihat Produk -->
                            <button type="button" class="btn btn-info btn-sm text-white" 
                                data-bs-toggle="modal" 
                                data-bs-target="#productDetailModal{{ $category->category_id }}">
                                <i class="mdi mdi-eye btn-icon-prepend"></i> Lihat Produk
                            </button>
                            <!-- Tombol Delete -->
                            <button class="btn btn-sm btn-danger delete-category" 
                                data-id="{{ $category->category_id }}" 
                                data-name="{{ $category->category_name }}" 
                                data-product-count="{{ $category->products->count() }}">
                                <i class="mdi mdi-delete btn-icon-prepend"></i> Hapus
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const deleteButtons = document.querySelectorAll('.delete-category');
        
                deleteButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const categoryId = this.getAttribute('data-id');
                        const categoryName = this.getAttribute('data-name');
                        const productCount = parseInt(this.getAttribute('data-product-count'), 10); // Pastikan ini angka
        
                        if (productCount > 0) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Tidak Dapat Menghapus',
                                text: `Kategori "${categoryName}" masih memiliki produk, Kosongkan produk terlebih dahulu.`,
                            });
                        } else {
                            Swal.fire({
                                title: 'Apakah Anda yakin?',
                                text: `Anda akan menghapus kategori "${categoryName}".`,
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#d33',
                                cancelButtonColor: '#3085d6',
                                confirmButtonText: 'Ya, Hapus!',
                                cancelButtonText: 'Batal'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    fetch(`/kelola/produk/category/${categoryId}`, {
                                        method: 'DELETE',
                                        headers: {
                                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                            'Content-Type': 'application/json',
                                        },
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            Swal.fire({
                                                icon: 'success',
                                                title: 'Berhasil!',
                                                text: 'Kategori berhasil dihapus.',
                                                timer: 2000,
                                                showConfirmButton: false
                                            }).then(() => {
                                                location.reload(); // Reload halaman
                                            });
                                        } else {
                                            Swal.fire({
                                                icon: 'error',
                                                title: 'Gagal!',
                                                text: data.error,
                                            });
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error:', error);
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'Kesalahan!',
                                            text: 'Terjadi kesalahan saat menghapus kategori.',
                                        });
                                    });
                                }
                            });
                        }
                    });
                });
            });
        </script>
        
        
    



<!-- Tabel Daftar Produk -->
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="text-primary fw-bold">Daftar Produk</h3>

            <!-- Search Bar -->
            <div class="col-lg-6 col-md-6 d-flex justify-content-end">
                <div class="input-group rounded-lg shadow-sm" style="max-width: 300px; max-height: 35px;">
                    <input type="text" class="form-control h-50" id="search-product" placeholder="Cari Produk" aria-label="search" aria-describedby="search">
                    <div class="input-group-prepend hover-cursor h-50">
                        <span class="input-group-text bg-primary text-white h-100 d-flex align-items-center justify-content-center">
                            <i class="icon-search"></i>
                        </span>
                    </div>
                </div>
            </div>

            <button type="button" class="btn btn-success btn-icon-text text-white" data-bs-toggle="modal" data-bs-target="#addProductModal">
                <i class="mdi mdi-loupe btn-icon-prepend"></i>Tambah Produk
            </button>
        </div>

        <div class="table-responsive scrollable-table-container mt-2">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Kode Barang</th>
                        <th>Nama Barang</th>
                        <th>Stok</th>
                        <th>Harga Jual</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="productTableBody">
                    @foreach($products as $product)
                    <tr class="product-row">
                        <td><img src="data:image/png;base64,{{ $product->getBarcodeBase64() }}" alt="Barcode"></td>
                        <td>{{ $product->product_name }}</td>
                        <td>{{ $product->quantity_in_stock }}</td>
                        <td>Rp{{ number_format($product->harga_jual, 0, '.', '.') }}</td>
                        <td>
                            <!-- Tombol Edit -->
                            <a href="#" data-bs-toggle="modal" data-bs-target="#editProductModal{{ $product->product_id }}" class="btn btn-sm btn-warning text-white me-2">
                                <i class="mdi mdi-pencil"></i> Edit
                            </a>
                            <!-- Tombol Delete dengan SweetAlert2 -->
                            <button class="btn btn-sm btn-danger delete-product" data-id="{{ $product->product_id }}" data-name="{{ $product->product_name }}">
                                <i class="mdi mdi-delete"></i> Hapus Produk
                            </button>
                            <!-- Tombol Tambah Stok -->
                            <button class="btn btn-success btn-sm tambah-stok" data-bs-toggle="modal" data-bs-target="#stokModal" data-mode="tambah" data-kode="{{ $product->product_id }}" data-nama="{{ $product->product_name }}">
                                Tambah Stok
                            </button>
                            <!-- Tombol Kurang Stok -->
                            <button class="btn btn-warning btn-sm kurang-stok" data-bs-toggle="modal" data-bs-target="#stokModal" data-mode="kurang" data-kode="{{ $product->product_id }}" data-nama="{{ $product->product_name }}">
                                Kurang Stok
                            </button>
                            <!-- Tombol Print Barcode -->
                            <button type="button" class="btn btn-primary btn-sm btn-icon-text text-white"
                                data-bs-toggle="modal" data-bs-target="#printBarcodeModal"
                                onclick="openPrintModal('{{ $product->product_id }}')">
                                <i class="mdi mdi-printer btn-icon-prepend"></i> Print Barcode
                            </button>

                            
                        </td>
                    </tr>

                    <!-- Modal Edit Produk -->
                    <div class="modal fade" id="editProductModal{{ $product->product_id }}" tabindex="-1" aria-labelledby="editProductModalLabel{{ $product->product_id }}" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editProductModalLabel{{ $product->product_id }}">Edit Produk</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <form action="{{ route('products.update', $product->product_id) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <div class="mb-3">
                                            <label for="product_name{{ $product->product_id }}" class="form-label">Nama Produk</label>
                                            <input type="text" class="form-control" id="product_name{{ $product->product_id }}" name="product_name" value="{{ $product->product_name }}" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="category_id{{ $product->product_id }}" class="form-label">Kategori</label>
                                            <select class="form-select" id="category_id{{ $product->product_id }}" name="category_id" required>
                                                @foreach($categories as $category)
                                                <option value="{{ $category->category_id }}" {{ $product->category_id == $category->category_id ? 'selected' : '' }}>
                                                    {{ $category->category_name }}
                                                </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="quantity_in_stock{{ $product->product_id }}" class="form-label">Jumlah Stok</label>
                                            <input type="number" class="form-control" id="quantity_in_stock{{ $product->product_id }}" name="quantity_in_stock" value="{{ $product->quantity_in_stock }}" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="harga_beli{{ $product->product_id }}" class="form-label">Harga Beli</label>
                                            <div class="input-group">
                                                <button class="btn btn-outline-secondary" type="button" onclick="changeValue('harga_beli{{ $product->product_id }}', -1000)">-</button>
                                                <input type="text" 
                                                    class="form-control text-end" 
                                                    id="harga_beli{{ $product->product_id }}" 
                                                    name="harga_beli" 
                                                    value="{{ number_format($product->harga_beli, 0, ',', '.') }}" 
                                                    oninput="formatRupiah(this)" 
                                                    required>
                                                <button class="btn btn-outline-secondary" type="button" onclick="changeValue('harga_beli{{ $product->product_id }}', 1000)">+</button>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="harga_jual{{ $product->product_id }}" class="form-label">Harga Jual</label>
                                            <div class="input-group">
                                                <button class="btn btn-outline-secondary" type="button" onclick="changeValue('harga_jual{{ $product->product_id }}', -1000)">-</button>
                                                <input type="text" 
                                                    class="form-control text-end" 
                                                    id="harga_jual{{ $product->product_id }}" 
                                                    name="harga_jual" 
                                                    value="{{ number_format($product->harga_jual, 0, ',', '.') }}" 
                                                    oninput="formatRupiah(this)" 
                                                    required>
                                                <button class="btn btn-outline-secondary" type="button" onclick="changeValue('harga_jual{{ $product->product_id }}', 1000)">+</button>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="kode_perkiraan{{ $product->product_id }}" class="form-label">Kode Perkiraan (opsional)</label>
                                            <input type="text" class="form-control" id="kode_perkiraan{{ $product->product_id }}" name="kode_perkiraan" value="{{ $product->kode_perkiraan }}">
                                        </div>

                                        <button type="submit" class="btn btn-primary">Update Produk</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal untuk Tambah Kategori -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCategoryModalLabel">Tambah Kategori</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Form Tambah Kategori -->
                <form id="addCategoryForm" action="{{ route('products.category.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="category_name" class="form-label">Nama Kategori</label>
                        <input type="text" class="form-control" id="category_name" name="category_name" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Simpan Kategori</button>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal untuk Tambah Produk -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addProductModalLabel">Tambah Produk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addProductForm" action="{{ route('products.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="product_name" class="form-label">Nama Produk</label>
                        <input type="text" class="form-control" id="product_name" name="product_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Kategori</label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">Pilih Kategori</option>
                            @foreach($categories as $category)
                            <option value="{{ $category->category_id }}">{{ $category->category_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="quantity_in_stock" class="form-label">Jumlah Stok</label>
                        <input type="number" class="form-control" id="quantity_in_stock" name="quantity_in_stock" required>
                    </div>

                    <div class="mb-3">
                        <label for="harga_beli" class="form-label">Harga Beli</label>
                        <div class="input-group">
                            <button class="btn btn-outline-secondary" type="button" onclick="changeValue('harga_beli', -1000)">-</button>
                            <input type="text" 
                                class="form-control text-end" 
                                id="harga_beli" 
                                name="harga_beli" 
                                value="0" 
                                oninput="formatRupiah(this)" 
                                required>
                            <button class="btn btn-outline-secondary" type="button" onclick="changeValue('harga_beli', 1000)">+</button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="harga_jual" class="form-label">Harga Jual</label>
                        <div class="input-group">
                            <button class="btn btn-outline-secondary" type="button" onclick="changeValue('harga_jual', -1000)">-</button>
                            <input type="text" 
                                class="form-control text-end" 
                                id="harga_jual" 
                                name="harga_jual" 
                                value="0" 
                                oninput="formatRupiah(this)" 
                                required>
                            <button class="btn btn-outline-secondary" type="button" onclick="changeValue('harga_jual', 1000)">+</button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="kode_perkiraan" class="form-label">Kode Perkiraan (opsional)</label>
                        <input type="text" class="form-control" id="kode_perkiraan" name="kode_perkiraan">
                    </div>

                    <button type="submit" class="btn btn-primary">Simpan Produk</button>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal untuk Tambah/Kurang Stok -->
<div class="modal fade" id="stokModal" tabindex="-1" aria-labelledby="stokModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="stokModalLabel">Update Stok</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="stokForm" method="POST">
                @csrf
                <div class="modal-body">
                    <p><strong>Kode Barang:</strong> <span id="kodeProduk"></span></p>
                    <p><strong>Nama Barang:</strong> <span id="namaProduk"></span></p>
                    <input type="hidden" id="product_id" name="product_id">
                    <input type="hidden" id="mode" name="mode">
                    <div class="form-group">
                        <label for="jumlahStok" id="labelJumlah">Jumlah Stok</label>
                        <input type="number" id="jumlahStok" name="jumlah_stok" class="form-control" placeholder="Masukkan jumlah" min=1>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Print Barcode -->
<div class="modal fade" id="printBarcodeModal" tabindex="-1" aria-labelledby="printBarcodeLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('products.printBarcode') }}" target="_blank">
                @csrf
                <!-- Input hidden untuk mengirimkan product_id -->
                <input type="hidden" name="product_id" id="modal-product-id">

                <div class="modal-header">
                    <h5 class="modal-title" id="printBarcodeLabel">Print Barcode</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="print_quantity" class="form-label">Masukkan Jumlah Lembar</label>
                        <p><i>1 Lembar Kertas A4 dengan ukuran label 48.5 mm x 25.4 mm dapat mencetak hingga 44 barcode.</i></p>
                        <input type="number" class="form-control" id="print_quantity" name="print_quantity" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Print</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript untuk mengisi product_id -->
<script>
    function openPrintModal(productId) {
        console.log('Product ID:', productId); // Debugging
        document.getElementById('modal-product-id').value = productId;
    }
</script>


<!-- Modal untuk Detail Produk -->
@foreach ($categories as $category)
<div class="modal fade" id="productDetailModal{{ $category->category_id }}" tabindex="-1" aria-labelledby="productDetailModalLabel{{ $category->category_id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productDetailModalLabel{{ $category->category_id }}">
                    Produk dalam Kategori - {{ $category->category_name }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead style="background-color: #f4f6f7; color: #34495e;">
                            <tr>
                                <th>ID Produk</th>
                                <th>Nama Produk</th>
                                <th>Stok</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($category->products as $product)
                            <tr>
                                <td>{{ $product->product_id }}</td>
                                <td>{{ $product->product_name }}</td>
                                <td>{{ $product->quantity_in_stock }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endforeach


<script>
    function changeValue(inputId, step) {
        // Ambil elemen input
        const input = document.getElementById(inputId);

        // Hapus format ribuan untuk mendapatkan angka murni
        let currentValue = parseInt(input.value.replace(/\./g, ''), 10);
        console.log(input.value);

        // Tambahkan atau kurangi dengan step
        currentValue += step;
        console.log(step);

        // Pastikan nilai tidak negatif
        if (currentValue < 0) currentValue = 0;

        // Format ulang angka dengan pemisah ribuan
        input.value = currentValue.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        console.log(input.value)
    }

    function formatRupiah(input) {
        // Hapus karakter non-angka
        let value = input.value.replace(/[^0-9]/g, '');
        if (value) {
            // Tambahkan tanda titik setiap 3 digit
            input.value = value.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }
    }

</script>


<script>
    document.getElementById('addProductForm').addEventListener('submit', function(event) {
        // Ambil elemen input harga_beli dan harga_jual
        let hargaBeli = document.getElementById('harga_beli');
        let hargaJual = document.getElementById('harga_jual');

        // Hapus pemisah ribuan (titik) sebelum dikirim
        hargaBeli.value = hargaBeli.value.replace(/\./g, '');
        hargaJual.value = hargaJual.value.replace(/\./g, '');
    });

</script>

{{-- <script>
    document.addEventListener('DOMContentLoaded', function() {
        const deleteButtons = document.querySelectorAll('.delete-category');

        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const categoryId = this.getAttribute('data-id');
                const categoryName = this.getAttribute('data-name');
                const productCount = this.getAttribute('data-product-count');

                if (productCount > 0) {
                    alert('Tidak dapat menghapus kategori "' + categoryName + '" karena masih ada produk yang terkait.');
                } else {
                    if (confirm('Apakah Anda yakin ingin menghapus kategori "' + categoryName + '"?')) {
                        // If confirmed, send a DELETE request
                        fetch(`/kelola/produk/category/${categoryId}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json',
                            },
                        })
                        .then(response => {
                            if (response.ok) {
                                location.reload(); // Reload the page after deletion
                            } else {
                                alert('Gagal menghapus kategori.');
                            }
                        });
                    }
                }
            });
        });
    });
</script> --}}

{{-- 
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const categoryTableBody = document.querySelector('tbody');

        categoryTableBody.addEventListener('click', function (event) {
            if (event.target.closest('.delete-category')) {
                const button = event.target.closest('.delete-category');
                const categoryId = button.getAttribute('data-id');
                const categoryName = button.getAttribute('data-name');
                const productCount = parseInt(button.getAttribute('data-product-count'));

                if (productCount > 0) {
                    // Jika masih ada produk di kategori
                    Swal.fire({
                        title: `Kategori ${categoryName} Masih Memiliki Produk!`,
                        text: "Silakan kosongkan kategori terlebih dahulu sebelum menghapus.",
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                } else {
                    // Konfirmasi penghapusan kategori
                    Swal.fire({
                        title: `Hapus Kategori: ${categoryName}?`,
                        text: "Anda tidak dapat mengembalikan data yang sudah dihapus!",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Hapus',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Buat form untuk menghapus kategori
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = `kelola/produk/category/${categoryId}`;// Tidak ada {id} di URL
                            form.innerHTML = `
                                @csrf
                                @method('DELETE')
                            `;
                            document.body.appendChild(form);
                            form.submit();

                        }
                    });
                }
            }
        });
    });
</script> --}}



<!-- JavaScript untuk SweetAlert2, Pencarian, dan Pagination -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {

        const productTableBody = document.getElementById('productTableBody');

        productTableBody.addEventListener('click', function (event) {
            if (event.target.closest('.delete-product')) {
                const button = event.target.closest('.delete-product');
                const productId = button.getAttribute('data-id');
                console.log(productId);
                const productName = button.getAttribute('data-name');
                
                Swal.fire({
                    title: `Hapus Produk: ${productName}?`,
                    text: "Anda tidak akan bisa mengembalikan data yang sudah dihapus!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Hapus',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = `/kelola/produk/${productId}`;
                        form.innerHTML = `
                            @csrf
                            @method('DELETE')
                        `;
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            }
        });

        productTableBody.addEventListener('click', function (event) {
            const stokButton = event.target.closest('.tambah-stok') || event.target.closest('.kurang-stok');
            if (stokButton) {
                const mode = stokButton.getAttribute('data-mode');
                const kode = stokButton.getAttribute('data-kode');
                const nama = stokButton.getAttribute('data-nama');

                document.getElementById('stokForm').setAttribute('action', `/kelola/produk/${kode}/update-stock`);
                document.getElementById('kodeProduk').innerText = kode;
                document.getElementById('namaProduk').innerText = nama;
                document.getElementById('mode').value = mode;
                document.getElementById('labelJumlah').innerText = mode === 'tambah' ? 'Tambah Stok' : 'Kurang Stok';
            }
        });
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Hide the success message after 2 seconds
        if (document.getElementById('success-message')) {
            setTimeout(function() {
                document.getElementById('success-message').style.display = 'none';
            }, 2000); // 2 seconds delay
        }

        // Reload page after form submission
        document.getElementById('addProductForm').addEventListener('submit', function(event) {
            setTimeout(function() {
                location.reload(); // Reload after 2 seconds
            }, 2000); // 2 seconds delay
        });
    });
</script>

<!-- JavaScript untuk Pencarian AJAX -->
<script>
    document.getElementById('search-product').addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();

        // Send AJAX request to server for search results
        fetch(`{{ route('products.search') }}?searchTerm=` + searchTerm)
            .then(response => response.json())
            .then(data => {
                let tbody = document.getElementById('productTableBody');
                tbody.innerHTML = ''; // Clear the table

                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center">No match found</td></tr>';
                    filteredRows = [];
                } else {
                    // Populate table with search results and update filteredRows for pagination
                    filteredRows = data.map(product => {
                        let row = document.createElement('tr');
                        row.className = 'product-row';
                        row.innerHTML = `
                            <td><img src="data:image/png;base64,{{ $product->getBarcodeBase64() }}" alt="Barcode"></td>
                            <td>${product.product_name}</td>
                            <td>${product.quantity_in_stock}</td>
                            <td>Rp${parseInt(product.harga_jual).toLocaleString('id-ID')}</td>
                            <td>
                                <!-- Tombol Edit -->
                                <a href="#" data-bs-toggle="modal" data-bs-target="#editProductModal${ product.product_id }" class="btn btn-sm btn-warning text-white me-2">
                                    <i class="mdi mdi-pencil"></i> Edit
                                </a>
                                <!-- Tombol Delete dengan SweetAlert2 -->
                                <button class="btn btn-sm btn-danger delete-product" data-id="${ product.product_id }" data-name="${ product.product_name }">
                                    <i class="mdi mdi-delete"></i> Delete
                                </button>
                                <!-- Tombol Tambah Stok -->
                                <button class="btn btn-success btn-sm tambah-stok" data-bs-toggle="modal" data-bs-target="#stokModal" data-mode="tambah" data-kode="${ product.product_id }" data-nama="${ product.product_name }">
                                    Tambah Stok
                                </button>
                                <!-- Tombol Kurang Stok -->
                                <button class="btn btn-warning btn-sm kurang-stok" data-bs-toggle="modal" data-bs-target="#stokModal" data-mode="kurang" data-kode="${ product.product_id }" data-nama="${ product.product_name }">
                                    Kurang Stok
                                <!-- Tombol Print Barcode -->
                                <button type="button" class="btn btn-primary btn-sm btn-icon-text text-white"
                                    data-bs-toggle="modal" data-bs-target="#printBarcodeModal"
                                    onclick="openPrintModal('${ product.product_id }')">
                                    <i class="mdi mdi-printer btn-icon-prepend"></i> Print Barcode
                                </button>

                            </td>
                        `;
                        tbody.appendChild(row);
                        return row;
                    });
                }

                // Reset pagination to the first page and apply display logic
                currentPage = 1;
                displayRows(currentPage, filteredRows);
                updatePaginationControls(filteredRows);
            })
            .catch(error => console.error('Error:', error));
    });
</script>

@endsection
