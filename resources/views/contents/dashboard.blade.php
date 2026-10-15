@extends('layouts.app')

@section('content')
<style>
    .list-wrapper {
        max-height: 250px;
        overflow-y: auto;
    }

    .table-responsive {
        max-width: 100%;
    }
    .report-image {
        width: 100%;
        height: auto;
        max-width: 150px;
        display: block;
        margin: 0 auto 10px auto;
    }
    /* .card {
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        border: none;
        border-radius: 8px;
        height: 100%;
    }

    .card-body p {
        margin-bottom: 0.5rem;
    }

    .fs-30 {
        font-size: 2rem;
    }

    .mb-4 {
        margin-bottom: 1.5rem !important;
    }

    .mb-2 {
        margin-bottom: 0.5rem !important;
    }

    .mt-4 {
        margin-top: 1.5rem !important;
    } */

    @media (max-width: 767.98px) {
        .report-section {
            flex-direction: column;
        }

        .report-section .col-md-3 {
            margin-bottom: 20px;
        }
    }
</style>

<div class="container-fluid">
    <!-- Laporan Harian -->
    <div class="row mb-4">
        <div class="col-12 text-end">
            <a href="{{ route('admin.monthlyreport') }}" class="btn btn-primary">
                <i class="mdi mdi-file-chart"></i> Laporan Bulanan
            </a>
        </div>
    </div>
    <div class="row">
        <!-- Gambar Laporan Harian -->
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="card text-center">
                <div class="card-body">
                    <img src="{{ asset('assets/images/laporanharian.png') }}" alt="Laporan Harian" class="report-image">
                </div>
            </div>
        </div>
        <!-- Pendapatan Hari Ini -->
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="card card-blue-1 text-center">
                <div class="card-body">
                    <p class="mb-2">Pendapatan Hari Ini</p>
                    <h3 class="fs-30">Rp{{ number_format($totalOmset, 0, ',', '.') }}</h3>
                    <small>
                        @if($omsetChange > 0)
                        <span class="text-success">▲ {{ $omsetChange }}%</span>
                        @elseif($omsetChange < 0)
                            <span class="text-danger">▼ {{ abs($omsetChange) }}%</span>
                            @else
                            <span class="text-muted">0%</span>
                            @endif
                    </small>
                </div>
            </div>
        </div>

        <!-- Keuntungan Hari Ini -->
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="card card-dark-blue text-center">
                <div class="card-body">
                    <p class="mb-2">Keuntungan Hari Ini</p>
                    <h3 class="fs-30">Rp{{ number_format($totalKeuntungan, 0, ',', '.') }}</h3>
                    <small>
                        @if($keuntunganChange > 0)
                        <span class="text-success">▲ {{ $keuntunganChange }}%</span>
                        @elseif($keuntunganChange < 0)
                            <span class="text-danger">▼ {{ abs($keuntunganChange) }}%</span>
                            @else
                            <span class="text-muted">0%</span>
                            @endif
                    </small>
                </div>
            </div>
        </div>

        <!-- Total Pelanggan Hari Ini -->
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="card card-blue-2 text-center">
                <div class="card-body">
                    <p class="mb-2">Total Pelanggan</p>
                    <h3 class="fs-30">{{ $jumlahTransaksi }}</h3>
                    <small>
                        @if($transaksiChange > 0)
                        <span class="text-success">▲ {{ $transaksiChange }}%</span>
                        @elseif($transaksiChange < 0)
                            <span class="text-danger">▼ {{ abs($transaksiChange) }}%</span>
                            @else
                            <span class="text-muted">0%</span>
                            @endif
                    </small>
                </div>
            </div>
        </div>
    </div>


    <!-- Tabel Transaksi Hari Ini -->
    <div class="row">
        <div class="col-md-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <p class="card-title">Transaksi Hari Ini</p>
                    <div class="row">
                        <div class="col-12">
                            <div class="table-responsive list-wrapper pt-2">
                                <table id="example" class="display expandable-table table-striped" style="width:100%">
                                    <thead class="sticky-top">
                                        <tr>
                                            <th>Waktu</th>
                                            <th>Jumlah Pembayaran</th>
                                            <th>Metode Pembayaran</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($riwayatTransaksi as $transaksi)
                                        <tr>
                                            <td>{{ \Carbon\Carbon::parse($transaksi->time)->format('H:i:s') }}</td>
                                            <td>Rp{{ number_format($transaksi->payment_amount, 0, ',', '.') }}</td>
                                            <td>{{ ucfirst(str_replace('_', ' ', $transaksi->payment_method)) }}</td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="3" class="text-center">Tidak ada transaksi hari ini</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Produk Terlaris -->
        <!-- <div class="col-md-6 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <p class="card-title">Produk Terlaris</p>
                    </div>
                    <canvas id="produkTerlarisChart"></canvas>
                </div>
            </div>
        </div> -->
        <div class="col-md-6 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <!-- Header dengan Filter Waktu -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p class="card-title">Produk Terlaris</p>
                        <!-- Filter Waktu dengan Style Dropdown -->
                        <div class="dropdown flex-md-grow-1 flex-xl-grow-0">
                            <button class="btn btn-sm btn-light bg-white dropdown-toggle" type="button" id="dropdownMenuFilter"
                                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="mdi mdi-calendar"></i> {{ ucfirst(str_replace('_', ' ', $produkFilter ?? 'all')) === 'All' ? 'All' : ucfirst(str_replace('_', ' ', $produkFilter ?? 'all')) }}
                            </button>
                            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuFilter">
                                <a class="dropdown-item filter-option" href="#" data-filter="all">All</a>
                                <a class="dropdown-item filter-option" href="#" data-filter="7_days">7 Hari Terakhir</a>
                                <a class="dropdown-item filter-option" href="#" data-filter="30_days">30 Hari Terakhir</a>
                            </div>
                        </div>
                    </div>
                    <!-- Canvas untuk Chart -->
                    <canvas id="produkTerlarisChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Produk Hampir Habis -->
        <div class="col-md-6 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <p class="card-title">Produk Hampir Habis</p>
                    <div class="row">
                        <div class="col-12">
                            <div class="table-responsive list-wrapper pt-2">
                                <table id="produkHampirHabisTable" class="display expandable-table table-striped" style="width:100%">
                                    <thead class="sticky-top">
                                        <tr>
                                            <th>Produk</th>
                                            <th>Stok</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($produkHampirHabis as $produk)
                                        <tr>
                                            <td>{{ $produk->product_name }}</td>
                                            <td>{{ $produk->quantity_in_stock }}</td>
                                            <td>
                                                @if ($produk->quantity_in_stock == 0)
                                                <div class="badge badge-danger">Habis</div>
                                                @elseif ($produk->quantity_in_stock > 0 && $produk->quantity_in_stock <= 5)
                                                    <div class="badge badge-warning">Hampir Habis</div>
                            @else
                            <div class="badge badge-success">Stok Cukup</div>
                            @endif
                            </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center">Tidak ada produk hampir habis</td>
                            </tr>
                            @endforelse
                            </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Inisialisasi data awal
        const produkTerlarisLabels = @json($produkTerlaris->pluck('product_name'));
        const produkTerlarisData = @json($produkTerlaris->pluck('total_terjual'));

        // Inisialisasi Chart
        const ctxTerlaris = document.getElementById('produkTerlarisChart').getContext('2d');
        let produkTerlarisChart = new Chart(ctxTerlaris, {
            type: 'bar',
            data: {
                labels: produkTerlarisLabels,
                datasets: [{
                    label: 'Total Terjual',
                    data: produkTerlarisData,
                    backgroundColor: 'rgba(75, 73, 172, 0.6)',
                    borderColor: 'rgba(75, 73, 172, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        // Event Listener untuk Filter Dropdown
        const filterOptions = document.querySelectorAll('.filter-option');
        const dropdownButton = document.getElementById('dropdownMenuFilter');

        filterOptions.forEach(option => {
            option.addEventListener('click', function (e) {
                e.preventDefault();
                const filterValue = this.getAttribute('data-filter');

                // Update label pada tombol dropdown
                let displayText = '';
                if (filterValue === 'all') {
                    displayText = 'All';
                } else if (filterValue === '7_days') {
                    displayText = '7 Hari Terakhir';
                } else if (filterValue === '30_days') {
                    displayText = '30 Hari Terakhir';
                }
                dropdownButton.innerHTML = `<i class="mdi mdi-calendar"></i> ${displayText}`;

                // Kirim AJAX Request menggunakan Fetch API
                fetch(`{{ route('dashboard.produkTerlaris') }}?produk_filter=${filterValue}`)
                    .then(response => response.json())
                    .then(data => {
                        // Update Chart
                        produkTerlarisChart.data.labels = data.product_names;
                        produkTerlarisChart.data.datasets[0].data = data.total_terjual;
                        produkTerlarisChart.update();
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Terjadi kesalahan saat memuat data produk terlaris.');
                    });
            });
        });
    });
</script>

@endsection