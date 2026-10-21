@extends('invoice.layout')

@php($rp = fn ($n) => 'Rp' . number_format((float) $n, 0, ',', '.'))

@section('judul', 'NOTA PELUNASAN KREDIT BARANG')
@section('nomor', $kredit->nomor())

@section('isi')
    <table class="info">
        <tr><td style="width: 35%">Nama anggota</td><td>: {{ $kredit->user->name }}</td></tr>
        <tr><td>ID pegawai</td><td>: {{ $kredit->user->employee_id ?? '-' }}</td></tr>
        <tr><td>Jabatan</td><td>: {{ $kredit->user->jabatan ?? '-' }}</td></tr>
        <tr><td>Barang</td><td>: {{ $kredit->nama_barang }}</td></tr>
        <tr><td>Tanggal kredit</td><td>: {{ $kredit->tanggal_pengajuan->format('d/m/Y') }}</td></tr>
        <tr><td>Tanggal lunas</td><td>: {{ $kredit->dilunasi_pada->format('d/m/Y') }}</td></tr>
        <tr><td>Metode bayar</td><td>: {{ $kredit->metode_bayar === 'potong_gaji' ? 'Potong gaji' : 'Bayar sendiri' }}</td></tr>
    </table>

    <table class="rincian">
        <tr><th>Uraian</th><th class="kanan">Jumlah</th></tr>
        <tr><td>Pokok kredit ({{ $kredit->tenor }} bulan, bunga {{ (float) $kredit->bunga_persen }}% {{ $kredit->metode_bunga }})</td><td class="kanan">{{ $rp($kredit->pokok) }}</td></tr>
        @php($dipercepat = $kredit->angsuran->where('status', 'pelunasan')->count())
        <tr>
            <td>Angsuran terbayar{{ $dipercepat ? ", sisa {$dipercepat} bulan dilunasi dipercepat" : '' }}</td>
            <td class="kanan">{{ $kredit->angsuran->where('status', 'lunas')->count() }} dari {{ $kredit->tenor }}</td>
        </tr>
        <tr><td>Administrasi (dibayar di awal)</td><td class="kanan">{{ $rp($kredit->administrasi) }}</td></tr>
        <tr><td>Total pokok dibayar</td><td class="kanan">{{ $rp($kredit->pelunasan_pokok) }}</td></tr>
        <tr><td>Total bunga dibayar</td><td class="kanan">{{ $rp($kredit->pelunasan_bunga) }}</td></tr>
        <tr class="total"><td>Total pelunasan</td><td class="kanan">{{ $rp((float) $kredit->pelunasan_pokok + (float) $kredit->pelunasan_bunga) }}</td></tr>
    </table>

    <div class="cap">LUNAS</div>

    <table class="ttd">
        <tr><td>Anggota</td><td>Petugas Koperasi</td></tr>
        <tr><td style="padding-top: 40px">( {{ $kredit->user->name }} )</td><td style="padding-top: 40px">( ........................ )</td></tr>
    </table>
@endsection
