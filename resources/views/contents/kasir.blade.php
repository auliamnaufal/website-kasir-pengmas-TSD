@extends('layouts.app')

@section('content')
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/2.4.0/purify.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<style>
    .table-container-scrollable {
        max-height: 300px; /* Adjust this height as needed */
        overflow-y: auto; /* Enable vertical scrolling */
        border: 1px solid #ccc; /* Optional: Add a border for better visibility */
    }

    /* Optional: Customize scrollbar appearance */
    .table-container-scrollable::-webkit-scrollbar {
        width: 8px;
    }

    .table-container-scrollable::-webkit-scrollbar-thumb {
        background-color: #888;
        border-radius: 4px;
    }

    .table-container-scrollable::-webkit-scrollbar-thumb:hover {
        background-color: #555;
    }
</style>

<div id="content-placeholder">
    <div class="col-lg-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-6 col-md-6">
                        <h2 class="text-primary font-weight-bold mb-0">Kasir</h2>
                        <p class="card-description mt-0">{{ \Carbon\Carbon::now()->format('l, d F Y') }}</p>
                    </div>
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
                    <div class="col-lg-12 col-md-6 d-flex justify-content-end mt-0">
                        <div class="input-group rounded-lg shadow-sm" style="max-width: 300px; max-height: 35px;">
                            <div id="search-results" class="dropdown-menu mt-0 position-absolute w-100" aria-labelledby="search-product"></div>
                        </div>
                    </div>
                </div>
                <div id="no-products-message" class="text-center mt-2">
                    Masukkan produk
                </div>
                <div class="table-responsive mt-2" id="table-container" style="display: none;">
                    <table id="cashierTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th> Kode Barang </th>
                                <th> Nama Barang </th>
                                <th> Harga </th>
                                <th> Jumlah </th>
                                <th> Total </th>
                                <th> Aksi </th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>

                <!-- Total Pembayaran -->
                <div class="mt-4 d-flex justify-content-end">
                    <div class="col-lg-4">
                        <div class="border p-3 rounded bg-primary text-white">
                            <h4 class="font-weight-bold mb-1">Total Pembayaran:</h4>
                            <h3 class="font-weight-bold mb-0" id="total-payment">Rp 0</h3>
                        </div>
                    </div>
                </div>

                <!-- Opsi Pembayaran -->
                <div class="mt-4 d-flex justify-content-end">
                    <div class="dropdown">
                        <h5 class="font-weight-bold mb-3">Opsi Pembayaran</h5>
                        <button class="btn btn-inverse-primary dropdown-toggle" type="button" id="paymentDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            Pilih Metode Pembayaran
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="paymentDropdown">
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" id="qris" value="qris">
                                    <img src="{{ asset('assets/images/qris.png') }}" alt="QRIS" class="me-2" style="width: 30px; height: 30px;">
                                    Pembayaran QRIS
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="#" id="cash" value="cash">
                                    <img src="../../assets/images/qris.png" alt="Cash" class="me-2" style="width: 30px; height: 30px;">
                                    Pembayaran Tunai
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Tombol Proses Pembayaran -->
                <div class="mt-4 d-flex justify-content-end">
                    <button type="button" class="btn btn-success btn-lg text-white" id="processPaymentBtn" disabled>Proses Pembayaran</button>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal for Receipt -->
    <div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <!-- Header -->
                <div class="modal-header">
                    <h5 class="modal-title" id="receiptModalLabel">Receipt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <!-- Body -->
                <div class="modal-body" id="printable-receipt">
                    <div class="text-center">
                        <h2 class="font-weight-bold">My Store Name</h2>
                        <p>123 Example St, City, Country</p>
                        <p>{{ \Carbon\Carbon::now()->format('l, d F Y H:i:s') }}</p>
                    </div>
                    <hr>
                    <div>
                        <h5 class="font-weight-bold">Order Details:</h5>
                        <div class="table-container-scrollable">
                            <table id="receiptTable" class="table">
                                <thead>
                                    <tr>
                                        <th>Nama Barang</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody id="receiptDetails">
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <hr>
                    <div>
                        <h4 class="font-weight-bold text-end">Total Pembayaran: <span id="receiptTotal"></span></h5>
                        <h5 class="text-end">Metode Pembayaran: <span id="receiptPaymentMethod"></span></h6>
                    </div>
                </div>
                <!-- Footer -->
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary btn-struk">Cetak Struk</button>
                </div>
            </div>
        </div>
    </div>

</div>

<style>
    /* Existing styles */
    
    #receiptModal .table-container-scrollable {
        margin: 0; /* Remove any extra margin */
        padding: 0; /* Remove padding */
        overflow-y: auto; /* Ensure scrolling works if necessary */
    }

    #receiptModal .table {
        margin-bottom: 0; /* Remove extra space below the table */
        border-collapse: separate; /* Separate borders for modal-specific table */
    }

    #receiptModal .table th, 
    #receiptModal .table td {
        padding: 5px; /* Reduce padding to minimize gaps */
        border: none; /* Remove borders for a cleaner look */
        text-align: left; /* Align text properly */
    }

    #receiptModal .table th {
        font-size: 0.9rem; /* Adjust the header font size */
    }

    #receiptModal .table td {
        font-size: 0.85rem; /* Adjust the body font size */
    }

    /* Reduce spacing for modal-specific table container */
    #receiptModal .modal-body {
        padding: 10px; /* Reduce padding in the modal body */
    }
</style>

<!-- JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Fungsi untuk memperbarui total pembayaran
        function updateTotal() {
            let tableRows = document.querySelectorAll('#cashierTable tbody tr');
            let grandTotal = 0;

            tableRows.forEach(function(row) {
                let priceCell = row.cells[2].textContent.replace('Rp ', '').replace('.', '').replace(',', '');
                let quantityInput = row.querySelector('input.quantity-input');
                let quantity = quantityInput ? parseInt(quantityInput.value) : 0;

                // Hitung total per baris
                let total = (parseFloat(priceCell) * quantity) || 0;

                // Perbarui sel total per baris
                row.cells[4].textContent = 'Rp ' + total.toLocaleString('id-ID');

                // Tambahkan ke grand total
                grandTotal += total;
            });

            // Perbarui total pembayaran
            document.getElementById('total-payment').textContent = 'Rp ' + grandTotal.toLocaleString('id-ID');
        }

        // Ambil elemen tombol dan dropdown
        const processPaymentBtn = document.getElementById('processPaymentBtn');
        const paymentDropdown = document.getElementById('paymentDropdown');

        function checkProductInTable() {
            let tableRows = document.querySelectorAll('#cashierTable tbody tr');
            return tableRows.length > 0; // Mengembalikan true jika ada produk di tabel
        }


        function updatePaymentButtonState() {
            let paymentMethodSelected = paymentDropdown.textContent.trim() !== 'Pilih Metode Pembayaran';
            let hasProducts = checkProductInTable();

            // Aktifkan tombol hanya jika metode pembayaran dipilih dan ada produk di tabel
            processPaymentBtn.disabled = !(paymentMethodSelected && hasProducts);
        }

        document.addEventListener('input', function(event) {
            if (event.target.classList.contains('quantity-input')) {
                let maxStock = parseInt(event.target.getAttribute('max'));
                let currentQuantity = parseInt(event.target.value);

                if (currentQuantity > maxStock) {
                    event.target.value = maxStock; // Batasi nilai input sesuai dengan stok
                    alert(`Jumlah pesanan melebihi stok yang tersedia. Stok tersedia: ${maxStock}`);
                }
                updateTotal(); // Perbarui total saat jumlah berubah
            }
        });


        // Event listener untuk pilihan metode pembayaran
        document.getElementById('qris').addEventListener('click', function() {
            let paymentMethod = 'Pembayaran QRIS';
            paymentDropdown.textContent = paymentMethod;
            updatePaymentButtonState(); // Perbarui status tombol setelah memilih metode
        });

        document.getElementById('cash').addEventListener('click', function() {
            let paymentMethod = 'Pembayaran Tunai';
            paymentDropdown.textContent = paymentMethod;
            updatePaymentButtonState(); // Perbarui status tombol setelah memilih metode
        });

        // Fungsi untuk menghapus isi tabel kasir
        function clearCashierTable() {
            let tableBody = document.querySelector('#cashierTable tbody');
            tableBody.innerHTML = ''; // Kosongkan isi tabel
            document.getElementById('total-payment').textContent = 'Rp 0'; // Reset total pembayaran
        }

        let products = [];
        let paymentMethod = '';

        // Event listener untuk tombol Proses Pembayaran
        document.querySelector('.btn-success').addEventListener('click', function() {
            paymentMethod = document.getElementById('paymentDropdown').textContent.trim();
            document.getElementById('receiptPaymentMethod').textContent = paymentMethod;

            // Perbarui total pembayaran di modal
            let totalPayment = document.getElementById('total-payment').textContent;
            document.getElementById('receiptTotal').textContent = totalPayment;

            var myModal = new bootstrap.Modal(document.getElementById('receiptModal'));
            myModal.show();

            let tableRows = document.querySelectorAll('#cashierTable tbody tr');
            products = [];

            tableRows.forEach(function(row) {
                let productId = row.cells[0].textContent;
                let quantity = row.cells[3].querySelector('input.form-control').value;
                let price = row.cells[2].textContent.replace('Rp ', '').replace('.', '').replace(',', '');

                products.push({
                    id: productId,
                    quantity: quantity,
                    price: price,
                });
            });

            fetch('{{route('kasir.prosespembayaran')}}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            payment_method: paymentMethod,
                            products: products
                        })
                    })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Pembayaran berhasil diproses.');
                        console.log(data.receipt);
                        
                        // Update receipt modal with server data
                        let receiptDetails = document.getElementById('receiptDetails');
                        receiptDetails.innerHTML = ''; // Clear previous receipt details

                        data.receipt.order_details.forEach(item => {
                            let row = `
                                <tr>
                                    <td>
                                        <div><strong>${item.product_name}</strong></div>
                                        <div>${item.quantity} x Rp ${parseInt(item.unit_price).toLocaleString('id-ID')}</div>
                                    </td>
                                    <td>Rp ${parseInt(item.total_price).toLocaleString('id-ID')}</td>
                                </tr>`;
                            receiptDetails.innerHTML += row;
                        });

                        // Show the receipt modal
                        var myModal = new bootstrap.Modal(document.getElementById('receiptModal'));
                        myModal.show();

                        // Clear cashier table
                        clearCashierTable();
                        processPaymentBtn.disabled = true
                    } else {
                        alert('Terjadi kesalahan saat memproses pembayaran.');
                    }
                })
                .catch(error => console.error('Error:', error));
        });

        // Event listener untuk pilihan metode pembayaran
        document.getElementById('qris').addEventListener('click', function() {
            let paymentMethod = 'Pembayaran QRIS';
            document.getElementById('paymentDropdown').textContent = paymentMethod;
        });

        document.getElementById('cash').addEventListener('click', function() {
            let paymentMethod = 'Pembayaran Tunai';
            document.getElementById('paymentDropdown').textContent = paymentMethod;
        });


        let searchInput = document.getElementById('search-product');
        let searchResults = document.getElementById('search-results');
        let typingTimer; // Timer identifier
        const debounceDelay = 300; // Delay in milliseconds (adjust as needed)

        // Debounce function to handle delayed input processing
        function debounceSearch() {
            clearTimeout(typingTimer); // Clear the previous timer
            typingTimer = setTimeout(() => {
                query = searchInput.value.trim();
                if (query.length > 0) {
                    fetch(`{{ route('kasir.search') }}?query=${query}`)
                        .then(response => response.json())
                        .then(data => {
                            searchResults.innerHTML = ''; // Clear previous results

                            if (data.length > 0) {
                                data.forEach(product => {
                                    let resultItem = `
                                        <div class="dropdown-item product-result mb-2" 
                                            data-id="${product.product_id}" 
                                            data-name="${product.product_name}" 
                                            data-price="${product.harga_jual}" 
                                            data-quantity="${product.quantity_in_stock}">
                                            <p class="mb-0"><strong>${product.product_name}</strong></p>
                                            <p class="mb-0">ID: ${product.product_id}</p>
                                            <p class="mb-0">Harga: Rp ${product.harga_jual}</p>
                                            <p class="mb-0">Stok: ${product.quantity_in_stock}</p>
                                        </div>`;
                                    searchResults.innerHTML += resultItem;
                                });
                                searchResults.classList.add('show'); // Show dropdown if there are results
                            } else {
                                searchResults.innerHTML = '<p>Produk tidak ditemukan</p>';
                                searchResults.classList.add('show'); // Show 'not found' message
                            }
                        })
                        .catch(error => console.error('Error:', error));
                } else {
                    searchResults.innerHTML = ''; // Clear the results
                    searchResults.classList.remove('show'); // Hide the dropdown
                }
            }, debounceDelay);
        }

        // Event listener for search input
        searchInput.addEventListener('input', function () {
            console.log('hei');
            debounceSearch();
        });

        // Handle Enter key explicitly
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                let query = searchInput.value.trim();

                if (query.length === 9) { // Specific ID length check
                    fetch(`{{ route('kasir.search') }}?query=${query}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.length > 0) {
                                let product = data[0];

                                if (parseInt(product.quantity_in_stock) > 0) {
                                    addProductToTable(product);
                                } else {
                                    alert('Produk ini tidak tersedia (stok habis).');
                                }

                                searchInput.value = '';
                                searchResults.innerHTML = '';
                                searchResults.classList.remove('show');
                            } else {
                                alert('Produk tidak ditemukan dengan ID yang dimasukkan.');
                            }
                        })
                        .catch(error => console.error('Error:', error));
                }

                e.preventDefault(); // Prevent form submission or other default behavior
            }
        });

        // Function to add the product to the table
        function addProductToTable(product) {
            let productId = product.product_id;
            let productName = product.product_name;
            let productPrice = product.harga_jual;
            let productStock = parseInt(product.quantity_in_stock);

            // Check if the product already exists in the table
            let existingRow = Array.from(document.querySelectorAll('#cashierTable tbody tr')).find(row => {
                return row.cells[0].textContent === String(productId); // Match by product ID
            });

            if (existingRow) {
                // If the product exists, increase the quantity
                let quantityInput = existingRow.querySelector('input.quantity-input');
                let currentQuantity = parseInt(quantityInput.value);

                if (currentQuantity < productStock) {
                    quantityInput.value = currentQuantity + 1;
                    updateTotal();
                } else {
                    alert(`Jumlah maksimum untuk produk ini telah tercapai. Stok tersedia: ${productStock}`);
                }
            } else {
                // If the product does not exist, add a new row
                let tableBody = document.querySelector('#cashierTable tbody');
                let newRow = `
                    <tr>
                        <td>${productId}</td>
                        <td>${productName}</td>
                        <td>Rp ${parseInt(productPrice).toLocaleString('id-ID')}</td>
                        <td>
                            <input type="number" value="1" class="form-control quantity-input" min="1" max="${productStock}" style="width: 100%;">
                        </td>
                        <td>Rp ${parseInt(productPrice).toLocaleString('id-ID')}</td>
                        <td><button type="button" class="btn btn-danger btn-sm delete-btn">Hapus</button></td>
                    </tr>`;
                tableBody.insertAdjacentHTML('beforeend', newRow);
                updateTotal();
            }

            updatePaymentButtonState();
            document.getElementById('table-container').style.display = 'block';
            document.getElementById('no-products-message').style.display = 'none';

            // Clear search input, hide dropdown, and remove cursor focus
            const searchInput = document.getElementById('search-product');
            searchInput.value = '';
            searchResults.innerHTML = '';
            searchResults.classList.remove('show');

            searchInput.blur();
        }

        // Event listener untuk menambahkan produk dari hasil pencarian ke tabel
        searchResults.addEventListener('click', function(event) {
            if (event.target.closest('.product-result')) {
                let selectedProduct = event.target.closest('.product-result');
                let productId = selectedProduct.getAttribute('data-id');
                let productName = selectedProduct.getAttribute('data-name');
                let productPrice = selectedProduct.getAttribute('data-price');
                let productStock = selectedProduct.getAttribute('data-quantity');

                // Cek apakah stok produk lebih dari 0 sebelum menambahkan ke tabel
                if (parseInt(productStock) <= 0) {
                    alert('Produk ini tidak tersedia (stok habis) dan tidak dapat ditambahkan ke tabel.');
                    return; // Keluar dari fungsi jika stok habis
                }

                // Cek apakah produk sudah ada di tabel
                let existingRow = Array.from(document.querySelectorAll('#cashierTable tbody tr')).find(row => {
                    return row.cells[0].textContent === productId; // Mencocokkan ID produk
                });

                if (existingRow) {
                    // Jika produk sudah ada, tambahkan jumlahnya
                    let quantityInput = existingRow.querySelector('input.quantity-input');
                    let currentQuantity = parseInt(quantityInput.value);
                    let maxStock = parseInt(quantityInput.getAttribute('max'));

                    if (currentQuantity < maxStock) {
                        quantityInput.value = currentQuantity + 1; // Tambah jumlah
                        updateTotal(); // Perbarui total
                    } else {
                        alert(`Jumlah maksimum untuk produk ini telah tercapai. Stok tersedia: ${maxStock}`);
                    }
                } else {
                    // Jika produk belum ada, tambahkan produk ke tabel
                    let tableBody = document.querySelector('#cashierTable tbody');
                    let newRow = `
                <tr>
                    <td>${productId}</td>
                    <td>${productName}</td>
                    <td>Rp ${parseInt(productPrice).toLocaleString('id-ID')}</td>
                    <td>
                        <input type="number" value="1" class="form-control quantity-input" min="1" max="${productStock}" style="width: 100%;">
                    </td>
                    <td>Rp ${parseInt(productPrice).toLocaleString('id-ID')}</td>
                    <td><button type="button" class="btn btn-danger btn-sm delete-btn">Hapus</button></td>
                </tr>`;
                    tableBody.insertAdjacentHTML('beforeend', newRow);

                    updateTotal(); // Perbarui total setelah menambahkan produk
                    updatePaymentButtonState();
                    document.getElementById('table-container').style.display = 'block';
                    document.getElementById('no-products-message').style.display = 'none';
                }

                // Kosongkan input pencarian dan hasil
                searchInput.value = '';
                searchResults.innerHTML = '';
                searchResults.classList.remove('show'); // Sembunyikan dropdown
            }
        });


        // Event listener untuk perubahan jumlah pada input quantity
        document.querySelector('#cashierTable tbody').addEventListener('input', function(event) {
            if (event.target.classList.contains('quantity-input')) {
                updateTotal(); // Panggil updateTotal saat jumlah berubah
                updatePaymentButtonState();
            }
        });

        document.querySelector('#cashierTable tbody').addEventListener('click', function(event) {
            if (event.target.classList.contains('delete-btn')) {
                // Remove the row from the table
                event.target.closest('tr').remove();
                // Update the total after deletion
                updateTotal();
                updatePaymentButtonState();

                if (document.querySelectorAll('#cashierTable tbody tr').length === 0) {
                    document.getElementById('table-container').style.display = 'none';
                    document.getElementById('no-products-message').style.display = 'block';
                }
            }
        });
    });

    document.getElementById('receiptModal').addEventListener('hidden.bs.modal', function () {
        // Remove modal open class
        document.body.classList.remove('modal-open');

        // Remove modal backdrop
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) backdrop.remove();
    });

    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('search-product').focus();
    });

    document.querySelector('.btn-struk').addEventListener('click', function () {
        const receiptContent = document.getElementById('printable-receipt').outerHTML;

        // Open a new window or tab
        const printWindow = window.open('', '_blank');

        // Write the receipt content into the new window
        printWindow.document.open();
        printWindow.document.write(`
            <html>
                <head>
                    <title>Receipt</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            margin: 20px;
                        }
                        .table {
                            width: 100%;
                            border-collapse: collapse;
                            margin: 20px 0;
                        }
                        .table th, .table td {
                            border: 1px solid #ddd;
                            padding: 8px;
                            text-align: left;
                        }
                        .text-center {
                            text-align: center;
                        }
                        .text-end {
                            text-align: right;
                        }
                        .font-weight-bold {
                            font-weight: bold;
                        }

                        /* Ensure the content is wider when printing */
                        @media print {
                            body {
                                font-size: 10px; /* Smaller font size */
                                line-height: 1.2; /* Compact line height */
                                margin: 5px; /* Maintain consistent margins */
                            }
                            #printable-receipt {
                                font-size: 10px; /* Match the smaller font size */
                                width: 99%; /* Ensure it fits nicely within the page width */
                                margin: auto; /* Center the receipt content */
                                border-bottom: 2px solid #000; 
                                padding-bottom: 10px; 
                            }
                            .table {
                                font-size: 10px; /* Smaller font for table content */
                                border-collapse: collapse; /* Remove space between cells */
                                width: 100%; /* Use full width for the table */
                            }
                            .table th, .table td {
                                padding: 1px; /* Reduce padding to make the table more compact */
                                border: none; /* Remove borders for a clean look */
                            }
                        }

                        #receiptModal .modal-body {
                            padding: 10px; /* Reduce padding inside the modal */
                        }
                        #receiptModal .modal-footer {
                            padding: 5px; /* Reduce padding in the footer */
                        }
                    </style>
                </head>
                <body onload="window.print(); window.close();">
                    ${receiptContent}
                </body>
            </html>
        `);
        printWindow.document.close();
        });

        document.addEventListener('keydown', function (event) {
            const searchInput = document.getElementById('search-product');
            const activeElement = document.activeElement;

            // Check if the active element is a quantity input
            const isTypingInQuantity = activeElement && activeElement.classList.contains('quantity-input');

            // Ensure the key pressed is a character (a-z, A-Z, 0-9, etc.) or a deletion key
            const isCharacter = event.key.length === 1;
            const isBackspaceOrDelete = event.key === 'Backspace' || event.key === 'Delete';

            // If not typing in quantity and the current focus is not on the search bar
            if (!isTypingInQuantity && activeElement.id !== 'search-product' && (isCharacter || isBackspaceOrDelete)) {
                // Focus on the search bar
                searchInput.focus();

                // If a character is pressed, append it; handle backspace/delete accordingly
                if (isCharacter) {
                    searchInput.value += event.key;
                } else if (event.key === 'Backspace') {
                    searchInput.value = searchInput.value.slice(0, -1);
                }

                // Trigger a native input event for the search input
                const inputEvent = new Event('input', { bubbles: true });
                searchInput.dispatchEvent(inputEvent);

                // Prevent default behavior of the key press
                event.preventDefault();
            }
        });
</script>

@endsection