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
                <h3 class="text-primary fw-bold mb-0">Pinjaman {{ $pinjaman->nomor() }}</h3>
                <p class="text-muted mb-0">{{ $pinjaman->user->employee_id }} — {{ $pinjaman->user->name }} {{ $pinjaman->user->jabatan ? "({$pinjaman->user->jabatan})" : '' }}</p>
            </div>
            <div>
                <a href="{{ route('simpan-pinjam.index') }}" class="btn btn-outline-secondary">Kembali</a>
                @if ($pinjaman->status === 'lunas')
                    <a href="{{ route('simpan-pinjam.invoice', $pinjaman) }}" target="_blank" class="btn btn-primary">
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
                    <tr><td>Status</td><td class="text-end fw-bold">{{ ucfirst($pinjaman->status) }}</td></tr>
                    @if ($pinjaman->alasan_penolakan)
                        <tr><td>Alasan penolakan</td><td class="text-end text-danger">{{ $pinjaman->alasan_penolakan }}</td></tr>
                    @endif
                    <tr><td>Tanggal pengajuan</td><td class="text-end">{{ $pinjaman->tanggal_pengajuan->format('d/m/Y') }}</td></tr>
                    <tr><td>Pokok</td><td class="text-end">{{ $rp($pinjaman->pokok) }}</td></tr>
                    <tr><td>Tenor / bunga</td><td class="text-end">{{ $pinjaman->tenor }} bulan / {{ (float) $pinjaman->bunga_persen }}% {{ $pinjaman->metode_bunga }}</td></tr>
                    <tr><td>Administrasi (pendapatan + sukarela)</td><td class="text-end">{{ $rp($pinjaman->administrasi) }} ({{ $rp($pinjaman->administrasi_pendapatan) }} + {{ $rp($pinjaman->administrasi_sukarela) }})</td></tr>
                    <tr><td>Kompensasi pinjaman lama</td><td class="text-end">{{ $rp($pinjaman->kompensasi) }}</td></tr>
                    <tr class="fw-bold"><td>Dana diterima</td><td class="text-end">{{ $rp($pinjaman->dana_diterima) }}</td></tr>
                    <tr><td>Metode bayar</td><td class="text-end">{{ $pinjaman->metode_bayar === 'potong_gaji' ? 'Potong gaji' : 'Bayar sendiri' }}</td></tr>
                    @if ($pinjaman->status === 'lunas')
                        <tr><td>Dilunasi</td><td class="text-end">{{ $pinjaman->dilunasi_pada->format('d/m/Y H:i') }}
                            @if ($pinjaman->dikompensasiOleh) (kompensasi {{ $pinjaman->dikompensasiOleh->nomor() }}) @endif</td></tr>
                        <tr><td>Total dibayar: pokok / bunga</td><td class="text-end">{{ $rp($pinjaman->pelunasan_pokok) }} / {{ $rp($pinjaman->pelunasan_bunga) }}</td></tr>
                    @endif
                </table>
                <p class="text-muted mb-0">Saldo simpanan anggota: wajib {{ $rp($saldoSimpanan['wajib']) }}, sukarela {{ $rp($saldoSimpanan['sukarela']) }}.</p>
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
                        (minimal bulan ke-{{ $pinjaman->bulan_bunga_minimum }}).
                    </p>
                    <table class="table table-sm table-ringkasan">
                        <tr><td>Sisa pokok</td><td class="text-end">{{ $rp($rincianPelunasan->sisaPokok) }}</td></tr>
                        <tr><td>Bunga ditagih</td><td class="text-end">{{ $rp($rincianPelunasan->bungaDitagih) }}</td></tr>
                        <tr><td>Bunga dibebaskan</td><td class="text-end">{{ $rp($rincianPelunasan->bungaDibebaskan) }}</td></tr>
                        <tr class="fw-bold"><td>Total pelunasan</td><td class="text-end">{{ $rp($rincianPelunasan->total) }}</td></tr>
                    </table>
                    <form action="{{ route('simpan-pinjam.lunasi', $pinjaman) }}" method="POST"
                        onsubmit="return confirm('Lunasi pinjaman ini sekarang sebesar {{ $rp($rincianPelunasan->total) }}?')">
                        @csrf
                        <button type="submit" class="btn btn-warning mt-3">Lunasi Sekarang</button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($pinjaman->angsuran->isNotEmpty())
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
                                @php($berikutnya = $pinjaman->angsuran->firstWhere('status', 'belum_lunas'))
                                @foreach ($pinjaman->angsuran as $angsuran)
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
                                            @elseif ($pinjaman->status === 'aktif' && $berikutnya && $berikutnya->id === $angsuran->id)
                                                {{-- Tombol di kolom status, bukan kolom sendiri: tabelnya sudah lebar dan kolom ekstra terdorong ke luar layar. --}}
                                                <form action="{{ route('simpan-pinjam.bayar-angsuran', $angsuran) }}" method="POST">
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
