@extends('layouts.app')

@section('content')
@php($rp = fn ($n) => 'Rp' . number_format((float) $n, 0, ',', '.'))
<style>
    /* Template mengeset td nowrap; nilai panjang di ringkasan harus bisa turun baris. */
    .table-ringkasan td { white-space: normal; }
</style>
<div class="row">
    <div class="col-12 grid-margin">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h3 class="text-primary fw-bold mb-0">Kredit {{ $kredit->nomor() }}</h3>
                <p class="text-muted mb-0">{{ $kredit->user->employee_id }} — {{ $kredit->user->name }} {{ $kredit->user->jabatan ? "({$kredit->user->jabatan})" : '' }}</p>
            </div>
            <div>
                <a href="{{ route('kredit.index') }}" class="btn btn-outline-secondary">Kembali</a>
                @if ($kredit->status === 'lunas')
                    <a href="{{ route('kredit.invoice', $kredit) }}" target="_blank" class="btn btn-primary">
                        <i class="mdi mdi-printer"></i> Cetak Invoice Pelunasan
                    </a>
                @endif
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="col-12"><div class="alert alert-success">{{ session('success') }}</div></div>
    @endif
    @if (session('error'))
        <div class="col-12"><div class="alert alert-danger">{{ session('error') }}</div></div>
    @endif
    @if ($errors->any())
        <div class="col-12">
            <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="col-lg-6 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Ringkasan</h4>
                <table class="table table-sm table-ringkasan">
                    <tr><td>Status</td><td class="text-end fw-bold">{{ $kredit->status === 'disetujui' ? 'Berjalan' : ucfirst($kredit->status) }}</td></tr>
                    @if ($kredit->alasan_penolakan)
                        <tr><td>Alasan penolakan</td><td class="text-end text-danger">{{ $kredit->alasan_penolakan }}</td></tr>
                    @endif
                    <tr><td>Barang</td><td class="text-end">{{ $kredit->nama_barang }}</td></tr>
                    <tr><td>Tanggal pengajuan</td><td class="text-end">{{ $kredit->tanggal_pengajuan->format('d/m/Y') }}</td></tr>
                    <tr><td>Pokok</td><td class="text-end">{{ $rp($kredit->pokok) }}</td></tr>
                    <tr><td>Tenor / bunga</td><td class="text-end">
                        {{ $kredit->tenor }} bulan / {{ (float) $kredit->bunga_persen }}% {{ $kredit->metode_bunga }}
                        @if ($kredit->bunga_sampai_bulan) (sampai bulan ke-{{ $kredit->bunga_sampai_bulan }}) @endif
                    </td></tr>
                    <tr><td>Administrasi (sekali)</td><td class="text-end">{{ $rp($kredit->administrasi) }}</td></tr>
                    <tr><td>Metode bayar</td><td class="text-end">{{ $kredit->metode_bayar === 'potong_gaji' ? 'Potong gaji' : 'Bayar sendiri' }}</td></tr>
                    @if ($kredit->status === 'lunas')
                        <tr><td>Dilunasi</td><td class="text-end">{{ $kredit->dilunasi_pada->format('d/m/Y H:i') }}</td></tr>
                        <tr><td>Total dibayar: pokok / bunga</td><td class="text-end">{{ $rp($kredit->pelunasan_pokok) }} / {{ $rp($kredit->pelunasan_bunga) }}</td></tr>
                    @endif
                </table>
            </div>
        </div>
    </div>

    @if ($rincianPelunasan)
        <div class="col-lg-6 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Pelunasan Dipercepat</h4>
                    <p class="card-description">
                        Dihitung setelah {{ $rincianPelunasan->angsuranTerbayar }} angsuran terbayar.
                        Bunga ditagih sampai bulan ke-{{ $rincianPelunasan->bungaSampaiBulan }}
                        (minimal bulan ke-{{ $kredit->bulan_bunga_minimum }}).
                    </p>
                    <table class="table table-sm table-ringkasan">
                        <tr><td>Sisa pokok</td><td class="text-end">{{ $rp($rincianPelunasan->sisaPokok) }}</td></tr>
                        <tr><td>Bunga ditagih</td><td class="text-end">{{ $rp($rincianPelunasan->bungaDitagih) }}</td></tr>
                        <tr><td>Bunga dibebaskan</td><td class="text-end">{{ $rp($rincianPelunasan->bungaDibebaskan) }}</td></tr>
                        <tr class="fw-bold"><td>Total pelunasan</td><td class="text-end">{{ $rp($rincianPelunasan->total) }}</td></tr>
                    </table>
                    <form action="{{ route('kredit.lunasi', $kredit) }}" method="POST"
                        onsubmit="return confirm('Lunasi kredit ini sekarang sebesar {{ $rp($rincianPelunasan->total) }}?')">
                        @csrf
                        <button type="submit" class="btn btn-warning">Lunasi Sekarang</button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($kredit->angsuran->isNotEmpty())
        <div class="col-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Jadwal Angsuran</h4>
                    <div class="table-responsive">
                        <table class="table table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>Ke</th><th>Jatuh Tempo</th><th>Pokok</th><th>Bunga</th><th>Total</th>
                                    <th>Sisa Pokok</th><th>Sisa Bunga</th><th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php($berikutnya = $kredit->angsuran->firstWhere('status', 'belum_lunas'))
                                @foreach ($kredit->angsuran as $angsuran)
                                    <tr>
                                        <td>{{ $angsuran->bulan_ke }}</td>
                                        <td>{{ $angsuran->jatuh_tempo->format('d/m/Y') }}</td>
                                        <td>{{ $rp($angsuran->pokok) }}</td>
                                        <td>{{ $rp($angsuran->bunga) }}</td>
                                        <td>{{ $rp($angsuran->total()) }}</td>
                                        <td>{{ $rp($angsuran->sisa_pokok) }}</td>
                                        <td>{{ $rp($angsuran->sisa_bunga) }}</td>
                                        <td>
                                            @if ($angsuran->status === 'lunas')
                                                <span class="badge badge-success">Lunas {{ $angsuran->dibayar_pada->format('d/m/Y') }}</span>
                                            @elseif ($angsuran->status === 'pelunasan')
                                                <span class="badge badge-secondary">Ditutup pelunasan</span>
                                            @elseif ($kredit->status === 'disetujui' && $berikutnya && $berikutnya->id === $angsuran->id)
                                                {{-- Tombol di kolom status: tabelnya sudah lebar, kolom ekstra terdorong ke luar layar. --}}
                                                <form action="{{ route('kredit.bayar-angsuran', $angsuran) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-success text-white">Bayar</button>
                                                </form>
                                            @else
                                                <span class="badge badge-warning">Belum lunas</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
