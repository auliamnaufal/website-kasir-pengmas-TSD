@extends('invoice.layout')

@php($rp = fn ($n) => 'Rp' . number_format((float) $n, 0, ',', '.'))

@section('judul', 'NOTA PELUNASAN PINJAMAN')
@section('nomor', $pinjaman->nomor())

@section('isi')
    <table class="info">
        <tr><td style="width: 35%">Nama anggota</td><td>: {{ $pinjaman->user->name }}</td></tr>
        <tr><td>ID pegawai</td><td>: {{ $pinjaman->user->employee_id ?? '-' }}</td></tr>
        <tr><td>Jabatan</td><td>: {{ $pinjaman->user->jabatan ?? '-' }}</td></tr>
        <tr><td>Tanggal pinjaman</td><td>: {{ $pinjaman->tanggal_pengajuan->format('d/m/Y') }}</td></tr>
        <tr><td>Tanggal lunas</td><td>: {{ $pinjaman->dilunasi_pada->format('d/m/Y') }}</td></tr>
        <tr><td>Metode bayar</td><td>: {{ $pinjaman->metode_bayar === 'potong_gaji' ? 'Potong gaji' : 'Bayar sendiri' }}</td></tr>
        @if ($pinjaman->dikompensasiOleh)
            <tr><td>Keterangan</td><td>: Dilunasi melalui kompensasi pinjaman {{ $pinjaman->dikompensasiOleh->nomor() }}</td></tr>
        @endif
    </table>

    <table class="rincian">
        <tr><th>Uraian</th><th class="kanan">Jumlah</th></tr>
        <tr><td>Pokok pinjaman ({{ $pinjaman->tenor }} bulan, bunga {{ (float) $pinjaman->bunga_persen }}% {{ $pinjaman->metode_bunga }})</td><td class="kanan">{{ $rp($pinjaman->pokok) }}</td></tr>
        @php($dipercepat = $pinjaman->angsuran->where('status', 'pelunasan')->count())
        <tr>
            <td>Angsuran terbayar{{ $dipercepat ? ", sisa {$dipercepat} bulan dilunasi dipercepat" : '' }}</td>
            <td class="kanan">{{ $pinjaman->angsuran->where('status', 'lunas')->count() }} dari {{ $pinjaman->tenor }}</td>
        </tr>
        <tr><td>Total pokok dibayar</td><td class="kanan">{{ $rp($pinjaman->pelunasan_pokok) }}</td></tr>
        <tr><td>Total bunga dibayar</td><td class="kanan">{{ $rp($pinjaman->pelunasan_bunga) }}</td></tr>
        <tr class="total"><td>Total pelunasan</td><td class="kanan">{{ $rp((float) $pinjaman->pelunasan_pokok + (float) $pinjaman->pelunasan_bunga) }}</td></tr>
    </table>

    <div class="cap">LUNAS</div>

    <table class="ttd">
        <tr><td>Anggota</td><td>Petugas Koperasi</td></tr>
        <tr><td style="padding-top: 40px">( {{ $pinjaman->user->name }} )</td><td style="padding-top: 40px">( ........................ )</td></tr>
    </table>
@endsection
