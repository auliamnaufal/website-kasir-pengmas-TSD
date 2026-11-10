@extends('layouts.app')

@section('content')
@php($rp = fn ($n) => 'Rp' . number_format((float) $n, 0, ',', '.'))
<div class="row">
    <div class="col-12 grid-margin">
        <div class="d-flex justify-content-between align-items-center">
            <h3 class="text-primary fw-bold mb-0">Kredit Pertokoan</h3>
            <a href="{{ route('kredit.laporan') }}" class="btn btn-primary">
                <i class="mdi mdi-file-chart"></i> Laporan Kredit Bulanan
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="col-12"><div class="alert alert-success">{{ session('success') }}</div></div>
    @endif
    @if ($errors->any())
        <div class="col-12">
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="col-lg-5 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Simulasi Kredit Barang</h4>
                <p class="card-description">
                    Plafon {{ $rp(config('kredit.plafon_tahunan')) }} per tahun,
                    tenor {{ config('kredit.tenor_min') }}–{{ config('kredit.tenor_max') }} bulan,
                    bunga {{ config('kredit.bunga_persen') }}% per bulan ({{ config('kredit.metode_bunga') }}@if (config('kredit.bunga_sampai_bulan')), sampai bulan ke-{{ config('kredit.bunga_sampai_bulan') }}@endif),
                    administrasi {{ config('kredit.administrasi_persen') }}%.
                </p>
                <form action="{{ route('kredit.index') }}" method="GET">
                    <div class="form-group">
                        <label for="user_id" class="form-label">Anggota</label>
                        <select name="user_id" id="user_id" class="form-select" required>
                            <option value="">Pilih anggota</option>
                            @foreach ($anggota as $user)
                                <option value="{{ $user->user_id }}" @selected(request('user_id') == $user->user_id)>
                                    {{ $user->employee_id }} — {{ $user->name }} {{ $user->jabatan ? "({$user->jabatan})" : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="nama_barang" class="form-label">Nama Barang</label>
                        <input type="text" name="nama_barang" id="nama_barang" class="form-control" maxlength="255" value="{{ request('nama_barang') }}" required>
                    </div>
                    <div class="form-group">
                        <label for="pokok" class="form-label">Harga Barang / Pokok Kredit (Rp)</label>
                        <input type="number" name="pokok" id="pokok" class="form-control" min="1" step="1" value="{{ request('pokok') }}" required>
                    </div>
                    <div class="form-group">
                        <label for="tenor" class="form-label">Tenor (bulan)</label>
                        <input type="number" name="tenor" id="tenor" class="form-control"
                            min="{{ config('kredit.tenor_min') }}" max="{{ config('kredit.tenor_max') }}"
                            value="{{ request('tenor', config('kredit.tenor_min')) }}" required>
                    </div>
                    <div class="form-group">
                        <label for="metode_bayar" class="form-label">Metode Bayar</label>
                        <select name="metode_bayar" id="metode_bayar" class="form-select" required>
                            <option value="potong_gaji" @selected(request('metode_bayar') === 'potong_gaji')>Potong gaji</option>
                            <option value="bayar_sendiri" @selected(request('metode_bayar') === 'bayar_sendiri')>Bayar sendiri</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Simulasi</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Hasil Simulasi</h4>
                @if ($simulasiError)
                    <div class="alert alert-danger">{{ $simulasiError }}</div>
                @elseif (! $simulasi)
                    <p class="text-muted">Isi form lalu tekan Simulasi untuk melihat jadwal angsuran sebelum mengajukan.</p>
                @else
                    @php($jadwal = $simulasi['jadwal'])
                    @if ($simulasi['alasan_penolakan'])
                        <div class="alert alert-danger">Akan ditolak sistem: {{ $simulasi['alasan_penolakan'] }}</div>
                    @endif
                    <table class="table table-sm">
                        <tr><td>Pokok kredit</td><td class="text-end">{{ $rp($jadwal->pokok) }}</td></tr>
                        <tr><td>Administrasi ({{ config('kredit.administrasi_persen') }}%, sekali)</td><td class="text-end">{{ $rp($simulasi['administrasi']) }}</td></tr>
                        <tr><td>Angsuran per bulan (bulan ke-1)</td><td class="text-end">{{ $rp($jadwal->baris[0]->total) }}</td></tr>
                        <tr><td>Total bunga</td><td class="text-end">{{ $rp($jadwal->totalBunga()) }}</td></tr>
                        <tr class="fw-bold"><td>Total angsuran</td><td class="text-end">{{ $rp($jadwal->totalAngsuran()) }}</td></tr>
                        <tr><td>Sisa plafon tahun ini (sebelum kredit ini)</td><td class="text-end">{{ $rp($simulasi['sisa_plafon']) }} dari {{ $rp($simulasi['plafon']) }}</td></tr>
                    </table>

                    @unless ($simulasi['alasan_penolakan'])
                        <form action="{{ route('kredit.store') }}" method="POST" class="mt-3">
                            @csrf
                            @foreach (['user_id', 'nama_barang', 'pokok', 'tenor', 'metode_bayar'] as $field)
                                <input type="hidden" name="{{ $field }}" value="{{ request($field) }}">
                            @endforeach
                            <button type="submit" class="btn btn-success text-white">Ajukan Kredit</button>
                        </form>
                    @endunless

                    <div class="table-responsive mt-3" style="max-height: 300px; overflow-y: auto;">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr><th>Ke</th><th>Jatuh Tempo</th><th>Pokok</th><th>Bunga</th><th>Total</th><th>Sisa Pokok</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($jadwal->baris as $baris)
                                    <tr>
                                        <td>{{ $baris->bulanKe }}</td>
                                        <td>{{ $baris->jatuhTempo->format('d/m/Y') }}</td>
                                        <td>{{ $rp($baris->pokok) }}</td>
                                        <td>{{ $rp($baris->bunga) }}</td>
                                        <td>{{ $rp($baris->total) }}</td>
                                        <td>{{ $rp($baris->sisaPokok) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Daftar Kredit</h4>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>No.</th><th>Anggota</th><th>Barang</th><th>Tanggal</th><th>Pokok</th><th>Tenor</th>
                                <th>Metode Bayar</th><th>Status</th><th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($kredit as $k)
                                <tr>
                                    <td>{{ $k->nomor() }}</td>
                                    <td>{{ $k->user->name }}</td>
                                    <td>{{ $k->nama_barang }}</td>
                                    <td>{{ $k->tanggal_pengajuan->format('d/m/Y') }}</td>
                                    <td>{{ $rp($k->pokok) }}</td>
                                    <td>{{ $k->tenor }} bln</td>
                                    <td>{{ $k->metode_bayar === 'potong_gaji' ? 'Potong gaji' : 'Bayar sendiri' }}</td>
                                    <td>
                                        <span class="badge {{ ['disetujui' => 'badge-info', 'lunas' => 'badge-success', 'ditolak' => 'badge-danger'][$k->status] }}">
                                            {{ $k->status === 'disetujui' ? 'Berjalan' : ucfirst($k->status) }}
                                        </span>
                                    </td>
                                    <td><a href="{{ route('kredit.show', $k) }}" class="btn btn-sm btn-outline-primary">Detail</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="text-center text-muted">Belum ada kredit.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
