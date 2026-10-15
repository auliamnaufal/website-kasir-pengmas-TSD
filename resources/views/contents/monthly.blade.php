@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12 text-end">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">
                <i class="mdi mdi-file-chart"></i> Laporan Harian
            </a>
        </div>
    </div>
    <div class="row">
        <!-- Gambar Laporan Harian -->
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="card card-blue-1 text-center">
                <div class="card-body">
                    <p class="mb-2">Rata-Rata Omset Harian</p>
                    <h3 class="fs-30">Rp{{ number_format($rataRataPenjualanHarian, 0, ',', '.') }}</h3>
                </div>
            </div>
        </div>

        <!-- Pendapatan Hari Ini -->
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="card card-dark-blue text-center">
                <div class="card-body">
                    <p class="mb-2">Pendapatan Bulan Ini</p>
                    <h3 class="fs-30">Rp{{ number_format($totalOmsetBulanIni, 0, ',', '.') }}</h3>
                </div>
            </div>
        </div>

        <!-- Keuntungan Hari Ini -->
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="card card-blue-2 text-center">
                <div class="card-body">
                    <p class="mb-2">Keuntungan Bulan Ini</p>
                    <h3 class="fs-30">Rp{{ number_format($totalPendapatanBersih, 0, ',', '.') }}</h3>
                </div>
            </div>
        </div>

        <!-- Total Pelanggan Hari Ini -->
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="card card-light-blue text-center">
                <div class="card-body">
                    <p class="mb-2">Jumlah Pelanggan Bulan Ini</p>
                    <h3 class="fs-30">{{ $jumlahTransaksiBulanIni }}</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
    <!-- Chart Omset dan Keuntungan -->
        <div class="col-md-4 col-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <p class="card-title">Tren Omset dan Keuntungan Harian</p>
                    <canvas id="trenOmsetKeuntunganChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Doughnut Chart -->
        <div class="col-md-4 col-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <p class="card-title">Persentase Metode Pembayaran</p>
                    <canvas id="persentaseMetodePembayaranChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Barplot Penjualan dan Omset per Kategori -->
        <div class="col-md-4 col-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <p class="card-title">Penjualan Per Kategori Produk</p>
                    <select id="kategoriDropdown" class="form-select mb-3">
                        <option value="penjualan" selected>Total Penjualan Sebulan</option>
                        <option value="omset">Total Omset Sebulan</option>
                    </select>
                    <canvas id="barplotPenjualanKategori"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive list-wrapper pt-2">
                    <table id="productTable" class="display expandable-table table-striped" style="width:100%">
                        <thead class="sticky-top">
                            <tr>
                                <th>Nama Produk</th>
                                <th>Kategori</th>
                                <th>Harga Beli</th>
                                <th>Harga Jual</th>
                                <th>Jumlah Terjual</th>
                                <th>Total Omset</th>
                                <th>Total Keuntungan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($produkReport as $produk)
                                <tr>
                                    <td>{{ $produk->product_name }}</td>
                                    <td>{{ $produk->category_name }}</td>
                                    <td>Rp{{ number_format($produk->harga_beli, 0, ',', '.') }}</td>
                                    <td>Rp{{ number_format($produk->harga_jual, 0, ',', '.') }}</td>
                                    <td>{{ $produk->total_terjual }}</td>
                                    <td>Rp{{ number_format($produk->total_omset, 0, ',', '.') }}</td>
                                    <td>Rp{{ number_format($produk->total_keuntungan, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const labels = @json($trenOmsetHarian->pluck('day')); // Label untuk sumbu X
        const omsetData = @json($trenOmsetHarian->pluck('total_omset')); // Data Omset
        const keuntunganData = @json($trenKeuntunganHarian->pluck('total_keuntungan')); // Data Keuntungan

        const ctx = document.getElementById('trenOmsetKeuntunganChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Total Omset',
                        data: omsetData,
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Total Keuntungan',
                        data: keuntunganData,
                        backgroundColor: 'rgba(255, 206, 86, 0.2)',
                        borderColor: 'rgba(255, 206, 86, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true, // Mengaktifkan aspek rasio
                aspectRatio: 1, // 1:1 untuk membuat persegi
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString(); // Format angka
                            }
                        }
                    }
                }
            }
        });


        // Doughnut Chart untuk Persentase Metode Pembayaran
        const metodeLabels = @json($persentaseMetodePembayaran->pluck('payment_method'));
        const metodeData = @json($persentaseMetodePembayaran->pluck('persentase'));

        const ctxMetode = document.getElementById('persentaseMetodePembayaranChart').getContext('2d');
        new Chart(ctxMetode, {
            type: 'doughnut',
            data: {
                labels: metodeLabels,
                datasets: [{
                    data: metodeData,
                    backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56'],
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 1,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                }
            }
        });

        const kategoriLabels = @json($penjualanPerKategori->pluck('category_name'));
        const totalPenjualan = @json($penjualanPerKategori->pluck('total_penjualan'));
        const totalOmset = @json($penjualanPerKategori->pluck('total_omset'));

        const ctxBar = document.getElementById('barplotPenjualanKategori').getContext('2d');

        // Konfigurasi awal untuk chart
        let currentData = totalPenjualan; // Default dataset
        let currentLabel = 'Total Penjualan'; // Default label

        const barChart = new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: kategoriLabels,
                datasets: [
                    {
                        label: currentLabel,
                        data: currentData,
                        backgroundColor: 'rgba(75, 192, 192, 0.6)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 1,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return value.toLocaleString(); // Format angka
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    }
                }
            }
        });

        // Event listener untuk dropdown
        const dropdown = document.getElementById('kategoriDropdown');
        dropdown.addEventListener('change', function () {
            const selectedValue = dropdown.value;

            if (selectedValue === 'penjualan') {
                currentData = totalPenjualan;
                currentLabel = 'Total Penjualan';
            } else if (selectedValue === 'omset') {
                currentData = totalOmset;
                currentLabel = 'Total Omset';
            }

            // Update data dan label pada chart
            barChart.data.datasets[0].data = currentData;
            barChart.data.datasets[0].label = currentLabel;
            barChart.update();
        });
    });
</script>
@endsection
