<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Barcode Produk {{ $product->product_name }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Ukuran kertas A4 */
        @page {
            size: A4;
            margin: 0;
        }

        body {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Header dengan tombol cetak */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 50px;
            background-color: #4B49AC; /* Warna header diperbarui */
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            z-index: 1000;
        }

        .header button {
            background-color: white;
            color: #4B49AC;
            border: 1px solid #4B49AC;
            padding: 5px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        .header button:hover {
            background-color: #4B49AC;
            color: white;
        }

        /* Tombol back */
        .back-button {
            background-color: white;
            color: #dc3545;
            border: 1px solid #dc3545;
            padding: 5px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        .back-button:hover {
            background-color: #dc3545;
            color: white;
        }

        /* Kontainer utama layout cetak */
        .page {
            display: grid;
            grid-template-columns: repeat(4, 48.5mm); /* 4 kolom */
            grid-auto-rows: 25.4mm; /* Tinggi setiap baris */
            gap: 0; /* Tidak ada jarak antar sticker */
            width: calc(210mm - 2cm); /* Lebar A4 dikurangi bingkai 1cm di kanan-kiri */
            height: calc(297mm - 2cm); /* Tinggi A4 dikurangi bingkai 1cm di atas-bawah */
            padding: 10mm; /* Bingkai 1cm di sekeliling */
            box-sizing: border-box;
            page-break-after: always; /* Pastikan setiap halaman terpisah */
            justify-content: center;
        }

        .barcode-item {
            width: 45.5mm;
            height: 23.4mm;
            text-align: center;
            padding: 2mm;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            box-sizing: border-box;
        }

        .barcode-item img {
            max-width: 100%;
            max-height: 15mm;
            font-size: 6px;
        }

        .details {
            display: flex;
            justify-content: space-between;
            font-size: 8px;
            width: 100%;
            margin-top: 2mm;
        }

        /* Sembunyikan header saat cetak */
        @media print {
            .header {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Header dengan tombol cetak -->
    <div class="header">
        <h5>Print Barcode Produk: {{ $product->product_name }}</h5>
        <div>
            <button onclick="window.print()">Cetak Barcode</button>
            <a href="/kelola/produk" class="back-button">Kembali ke Produk</a>
        </div>
    </div>

    <!-- Kontainer untuk barcode -->
    @for ($page = 0; $page < ceil($quantity / 44); $page++) <!-- Menghitung jumlah halaman -->
        <div class="page">
            @for ($i = 0; $i < 44; $i++) <!-- 44 barcode per halaman -->
                @php
                    $barcodeIndex = $page * 44 + $i;
                @endphp
                @if ($barcodeIndex < $quantity)
                    <div class="barcode-item">
                        <span>{{ $product->product_name }}</span>
                        <img src="data:image/png;base64,{{ $product->getBarcodeBase64() }}" alt="Barcode">
                        <div class="details">
                            <span>{{ $product->product_id }}</span>
                            <span>Rp{{ number_format($product->harga_jual, 2, ',', '.') }}</span>
                        </div>
                    </div>
                @endif
            @endfor
        </div>
    @endfor
</body>
</html>
