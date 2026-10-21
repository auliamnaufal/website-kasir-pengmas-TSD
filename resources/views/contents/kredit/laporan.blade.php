@extends('layouts.app')

@section('content')
@php($rp = fn ($n) => 'Rp' . number_format((float) $n, 0, ',', '.'))
<div class="row">
    <div class="col-12 grid-margin">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h3 class="text-primary fw-bold mb-0">Laporan Kredit {{ $bulan->translatedFormat('F Y') }}</h3>
                <a href="{{ route('kredit.index') }}">&laquo; Kembali ke Kredit Pertokoan</a>
            </div>
            <form action="{{ route('kredit.laporan') }}" method="GET" class="d-flex gap-2">
                <input type="month" name="bulan" class="form-control" value="{{ $bulan->format('Y-m') }}">
                <button type="submit" class="btn btn-primary">Tampilkan</button>
            </form>
        </div>
    </div>

    <div class="col-md-4 grid-margin stretch-card">
        <div class="card"><div class="card-body">
            <p class="mb-2">Angsuran pokok jatuh tempo</p>
            <h3 class="fs-30">{{ $rp($total_pokok) }}</h3>
        </div></div>
    </div>
    <div class="col-md-4 grid-margin stretch-card">
        <div class="card"><div class="card-body">
            <p class="mb-2">Angsuran bunga jatuh tempo</p>
            <h3 class="fs-30">{{ $rp($total_bunga) }}</h3>
        </div></div>
    </div>
    <div class="col-md-4 grid-margin stretch-card">
        <div class="card"><div class="card-body">
            <p class="mb-2">Pendapatan administrasi kredit</p>
            <h3 class="fs-30">{{ $rp($pendapatan_administrasi) }}</h3>
        </div></div>
    </div>

    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Angsuran per Anggota</h4>
                <p class="card-description">Sisa pokok dan sisa bunga dihitung setelah angsuran bulan ini dibayar.</p>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID Pegawai</th><th>Nama</th><th>Kredit</th><th>Pokok</th><th>Bunga</th><th>Total</th>
                                <th>Sisa Pokok</th><th>Sisa Bunga</th><th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($per_anggota as $baris)
                                <tr>
                                    <td>{{ $baris['anggota']->employee_id }}</td>
                                    <td>{{ $baris['anggota']->name }}</td>
                                    <td>{{ $baris['jumlah_kredit'] }}</td>
                                    <td>{{ $rp($baris['pokok']) }}</td>
                                    <td>{{ $rp($baris['bunga']) }}</td>
                                    <td>{{ $rp($baris['total']) }}</td>
                                    <td>{{ $rp($baris['sisa_pokok']) }}</td>
                                    <td>{{ $rp($baris['sisa_bunga']) }}</td>
                                    <td>{!! $baris['sudah_dibayar'] ? '<span class="badge badge-success">Dibayar</span>' : '<span class="badge badge-warning">Belum</span>' !!}</td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="text-center text-muted">Tidak ada angsuran jatuh tempo di bulan ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Kredit Lunas Bulan Ini</h4>
                <p class="card-description">
                    Total dibayar: pokok {{ $rp($lunas_pokok) }}, bunga {{ $rp($lunas_bunga) }}.
                </p>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr><th>No.</th><th>Anggota</th><th>Barang</th><th>Tanggal Lunas</th><th>Pokok Dibayar</th><th>Bunga Dibayar</th><th>Invoice</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($lunas as $k)
                                <tr>
                                    <td><a href="{{ route('kredit.show', $k) }}">{{ $k->nomor() }}</a></td>
                                    <td>{{ $k->user->name }}</td>
                                    <td>{{ $k->nama_barang }}</td>
                                    <td>{{ $k->dilunasi_pada->format('d/m/Y') }}</td>
                                    <td>{{ $rp($k->pelunasan_pokok) }}</td>
                                    <td>{{ $rp($k->pelunasan_bunga) }}</td>
                                    <td><a href="{{ route('kredit.invoice', $k) }}" target="_blank" class="btn btn-sm btn-outline-primary">PDF</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted">Belum ada kredit yang lunas di bulan ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
