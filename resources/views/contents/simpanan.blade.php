@extends('layouts.app')

@section('content')
@php($rp = fn ($n) => 'Rp' . number_format((float) $n, 0, ',', '.'))
<div class="row">
    <div class="col-12 grid-margin">
        <h3 class="text-primary fw-bold mb-0">Simpanan Anggota</h3>
    </div>

    @if (session('success'))
        <div class="col-12"><div class="alert alert-success">{{ session('success') }}</div></div>
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
                <h4 class="card-title">Simpanan Wajib</h4>
                <p class="card-description">
                    Nominal mengikuti jabatan (Konfigurasi Jabatan). Dicatat otomatis setiap tanggal 16;
                    tombol ini untuk mencatat manual. Anggota yang sudah tercatat di periode yang sama dilewati.
                </p>
                <form action="{{ route('simpanan.generate-wajib') }}" method="POST" class="d-flex gap-2">
                    @csrf
                    <input type="month" name="periode" class="form-control" value="{{ $periodeSekarang }}" required>
                    <button type="submit" class="btn btn-primary text-nowrap">Catat Simpanan Wajib</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Setor Simpanan Sukarela</h4>
                <form action="{{ route('simpanan.sukarela') }}" method="POST">
                    @csrf
                    <div class="mb-2">
                        <select name="user_id" class="form-select" required>
                            <option value="">Pilih anggota</option>
                            @foreach ($anggota as $baris)
                                <option value="{{ $baris['user']->user_id }}">{{ $baris['user']->employee_id }} — {{ $baris['user']->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <input type="number" name="jumlah" class="form-control" min="1" step="1" placeholder="Jumlah (Rp)" required>
                    </div>
                    <div class="mb-2">
                        <input type="text" name="keterangan" class="form-control" maxlength="255" placeholder="Keterangan (opsional)">
                    </div>
                    <button type="submit" class="btn btn-success text-white">Simpan</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Saldo per Anggota</h4>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr><th>ID Pegawai</th><th>Nama</th><th>Jabatan</th><th>Wajib</th><th>Sukarela</th><th>Total</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($anggota as $baris)
                                <tr>
                                    <td>{{ $baris['user']->employee_id }}</td>
                                    <td>{{ $baris['user']->name }}</td>
                                    <td>{{ $baris['user']->jabatan ?? '-' }}</td>
                                    <td>{{ $rp($baris['wajib']) }}</td>
                                    <td>{{ $rp($baris['sukarela']) }}</td>
                                    <td class="fw-bold">{{ $rp($baris['total']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted">Belum ada anggota.</td></tr>
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
                <h4 class="card-title">Riwayat Setoran Terakhir</h4>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr><th>Tanggal</th><th>Periode</th><th>Anggota</th><th>Jenis</th><th>Jumlah</th><th>Keterangan</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($riwayat as $s)
                                <tr>
                                    <td>{{ $s->tanggal->format('d/m/Y') }}</td>
                                    <td>{{ $s->periode }}</td>
                                    <td>{{ $s->user->name }}</td>
                                    <td>{{ ucfirst($s->jenis) }}</td>
                                    <td>{{ $rp($s->jumlah) }}</td>
                                    <td>{{ $s->keterangan }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted">Belum ada setoran.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
