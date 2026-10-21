@extends('layouts.app')

@section('content')
@php($rp = fn ($n) => 'Rp' . number_format((float) $n, 0, ',', '.'))
<div class="row">
    <div class="col-12 grid-margin">
        <div class="d-flex justify-content-between align-items-center">
            <h3 class="text-primary fw-bold mb-0">Simpan Pinjam</h3>
            <a href="{{ route('simpan-pinjam.laporan') }}" class="btn btn-primary">
                <i class="mdi mdi-file-chart"></i> Laporan Pelunasan Bulanan
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
                <h4 class="card-title">Simulasi Pinjaman Baru</h4>
                <p class="card-description">
                    Tenor {{ config('simpan_pinjam.tenor_min') }}–{{ config('simpan_pinjam.tenor_max') }} bulan,
                    bunga {{ config('simpan_pinjam.bunga_persen') }}% per bulan ({{ config('simpan_pinjam.metode_bunga') }}),
                    administrasi {{ config('simpan_pinjam.administrasi_pendapatan_persen') + config('simpan_pinjam.administrasi_sukarela_persen') }}%.
                </p>
                <form action="{{ route('simpan-pinjam.index') }}" method="GET">
                    <div class="mb-3">
                        <label for="user_id" class="form-label">Anggota</label>
                        <select name="user_id" id="user_id" class="form-control" required>
                            <option value="">Pilih anggota</option>
                            @foreach ($anggota as $user)
                                <option value="{{ $user->user_id }}" @selected(request('user_id') == $user->user_id)>
                                    {{ $user->employee_id }} — {{ $user->name }} {{ $user->jabatan ? "({$user->jabatan})" : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="pokok" class="form-label">Pokok Pinjaman (Rp)</label>
                        <input type="number" name="pokok" id="pokok" class="form-control" min="1" step="1" value="{{ request('pokok') }}" required>
                    </div>
                    <div class="mb-3">
                        <label for="tenor" class="form-label">Tenor (bulan)</label>
                        <input type="number" name="tenor" id="tenor" class="form-control"
                            min="{{ config('simpan_pinjam.tenor_min') }}" max="{{ config('simpan_pinjam.tenor_max') }}"
                            value="{{ request('tenor', config('simpan_pinjam.tenor_min')) }}" required>
                    </div>
                    <div class="mb-3">
                        <label for="metode_bayar" class="form-label">Metode Bayar</label>
                        <select name="metode_bayar" id="metode_bayar" class="form-control" required>
                            <option value="potong_gaji" @selected(request('metode_bayar') === 'potong_gaji')>Potong gaji</option>
                            <option value="bayar_sendiri" @selected(request('metode_bayar') === 'bayar_sendiri')>Bayar sendiri</option>
                        </select>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="kompensasi" value="1" id="kompensasi" class="form-check-input ms-0" @checked(request()->boolean('kompensasi'))>
                        <label for="kompensasi" class="form-check-label">Kompensasi: lunasi pinjaman aktif dari pinjaman baru ini</label>
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
                        <tr><td>Pokok pinjaman</td><td class="text-end">{{ $rp($jadwal->pokok) }}</td></tr>
                        <tr><td>Administrasi ke pendapatan koperasi</td><td class="text-end">− {{ $rp($simulasi['administrasi_pendapatan']) }}</td></tr>
                        <tr><td>Administrasi ke simpanan sukarela</td><td class="text-end">− {{ $rp($simulasi['administrasi_sukarela']) }}</td></tr>
                        @foreach ($simulasi['kompensasi'] as $k)
                            <tr>
                                <td>Kompensasi {{ $k['pinjaman']->nomor() }}
                                    <small class="text-muted d-block">pokok {{ $rp($k['rincian']->sisaPokok) }} + bunga {{ $rp($k['rincian']->bungaDitagih) }}</small>
                                </td>
                                <td class="text-end">− {{ $rp($k['rincian']->total) }}</td>
                            </tr>
                        @endforeach
                        <tr class="fw-bold"><td>Dana diterima anggota</td><td class="text-end">{{ $rp($simulasi['dana_diterima']) }}</td></tr>
                        <tr><td>Angsuran per bulan (bulan ke-1)</td><td class="text-end">{{ $rp($jadwal->baris[0]->total) }}</td></tr>
                        <tr><td>Total bunga</td><td class="text-end">{{ $rp($jadwal->totalBunga()) }}</td></tr>
                        <tr><td>Sisa plafon (sebelum pinjaman ini)</td><td class="text-end">{{ $rp($simulasi['sisa_plafon']) }} dari {{ $rp($simulasi['plafon']) }}</td></tr>
                    </table>

                    @unless ($simulasi['alasan_penolakan'])
                        <form action="{{ route('simpan-pinjam.store') }}" method="POST" class="mt-3">
                            @csrf
                            @foreach (['user_id', 'pokok', 'tenor', 'metode_bayar'] as $field)
                                <input type="hidden" name="{{ $field }}" value="{{ request($field) }}">
                            @endforeach
                            <input type="hidden" name="kompensasi" value="{{ request()->boolean('kompensasi') ? 1 : 0 }}">
                            <button type="submit" class="btn btn-success text-white">Ajukan Pinjaman</button>
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
                <h4 class="card-title">Daftar Pinjaman</h4>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>No.</th><th>Anggota</th><th>Tanggal</th><th>Pokok</th><th>Tenor</th>
                                <th>Metode Bayar</th><th>Status</th><th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($pinjaman as $p)
                                <tr>
                                    <td>{{ $p->nomor() }}</td>
                                    <td>{{ $p->user->name }}</td>
                                    <td>{{ $p->tanggal_pengajuan->format('d/m/Y') }}</td>
                                    <td>{{ $rp($p->pokok) }}</td>
                                    <td>{{ $p->tenor }} bln</td>
                                    <td>{{ $p->metode_bayar === 'potong_gaji' ? 'Potong gaji' : 'Bayar sendiri' }}</td>
                                    <td>
                                        <span class="badge {{ ['aktif' => 'badge-info', 'lunas' => 'badge-success', 'ditolak' => 'badge-danger'][$p->status] }}">
                                            {{ ucfirst($p->status) }}
                                        </span>
                                    </td>
                                    <td><a href="{{ route('simpan-pinjam.show', $p) }}" class="btn btn-sm btn-outline-primary">Detail</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted">Belum ada pinjaman.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
