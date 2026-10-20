@extends('layouts.app')

@section('content')
@php($rp = fn ($n) => 'Rp' . number_format((float) $n, 0, ',', '.'))
<div class="row">
    <div class="col-12 grid-margin">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="text-primary fw-bold mb-0">Laporan Pelunasan {{ $bulan->translatedFormat('F Y') }}</h3>
            <form action="{{ route('simpan-pinjam.laporan') }}" method="GET" class="d-flex gap-2">
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
            <p class="mb-2">Pendapatan administrasi pinjaman</p>
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
                                <th>ID Pegawai</th><th>Nama</th><th>Pinjaman</th><th>Pokok</th><th>Bunga</th><th>Total</th>
                                <th>Sisa Pokok</th><th>Sisa Bunga</th><th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($per_anggota as $baris)
                                <tr>
                                    <td>{{ $baris['anggota']->employee_id }}</td>
                                    <td>{{ $baris['anggota']->name }}</td>
                                    <td>{{ $baris['jumlah_pinjaman'] }}</td>
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
                <h4 class="card-title">Pinjaman Lunas Bulan Ini</h4>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr><th>No.</th><th>Anggota</th><th>Tanggal Lunas</th><th>Pokok Dibayar</th><th>Bunga Dibayar</th><th>Invoice</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($lunas as $p)
                                <tr>
                                    <td><a href="{{ route('simpan-pinjam.show', $p) }}">{{ $p->nomor() }}</a></td>
                                    <td>{{ $p->user->name }}</td>
                                    <td>{{ $p->dilunasi_pada->format('d/m/Y') }}</td>
                                    <td>{{ $rp($p->pelunasan_pokok) }}</td>
                                    <td>{{ $rp($p->pelunasan_bunga) }}</td>
                                    <td><a href="{{ route('simpan-pinjam.invoice', $p) }}" target="_blank" class="btn btn-sm btn-outline-primary">PDF</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted">Belum ada pinjaman yang lunas di bulan ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
