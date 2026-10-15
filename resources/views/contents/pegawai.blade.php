@extends('layouts.app')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">

            <!-- Form Pembuatan Akun Karyawan -->
            <div class="card">
                <div class="card-header bg-primary text-white text-center">
                    <h4 class="mb-0">Create Akun Karyawan</h4>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div id="successMessage" class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    <form action="{{ route('karyawan.index') }}" method="POST">
                        @csrf

                        <div class="form-group mb-3">
                            <label for="name" class="form-label">Nama Karyawan</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" id="name" value="{{ old('name') }}" required>
                            @error('name')
                                <span class="invalid-feedback" role="alert">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" id="email" value="{{ old('email') }}" required>
                            @error('email')
                                <span class="invalid-feedback" role="alert">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" id="password" required>
                            @error('password')
                                <span class="invalid-feedback" role="alert">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label for="password_confirmation" class="form-label">Confirm Password</label>
                            <input type="password" name="password_confirmation" class="form-control" id="password_confirmation" required>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Create Akun</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Daftar Akun Karyawan -->
            <div class="card mt-4">
                <div class="card-header bg-secondary text-white text-center">
                    <h5 class="mb-0">Daftar Akun Karyawan</h5>
                </div>
                <div class="card-body">
                    @if($karyawans->isEmpty())
                        <p class="text-center">Belum ada akun karyawan yang dibuat.</p>
                    @else
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead class="bg-light">
                                <tr>
                                    <th>No</th>
                                    <th>Nama</th>
                                    <th>Email</th>
                                    <th>Dibuat Pada</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($karyawans as $index => $karyawan)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $karyawan->name }}</td>
                                        <td>{{ $karyawan->email }}</td>
                                        <td>{{ $karyawan->created_at->format('d-m-Y H:i') }}</td>
                                        <td>
                                            <button 
                                                class="btn btn-danger btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteModal{{ $karyawan->id }}">
                                                Hapus
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Modal Konfirmasi Hapus -->
                                    <div class="modal fade" id="deleteModal{{ $karyawan->id }}" tabindex="-1" aria-labelledby="deleteModalLabel{{ $karyawan->id }}" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="deleteModalLabel{{ $karyawan->id }}">Konfirmasi Hapus</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Apakah Anda yakin ingin menghapus karyawan <strong>{{ $karyawan->name }}</strong> dengan email <strong>{{ $karyawan->email }}</strong>?</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <form action="{{ route('karyawan.destroy', $karyawan->email) }}" method="POST">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-danger">Hapus</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const deleteModal = document.getElementById('deleteModal');
    const deleteForm = document.getElementById('deleteForm');
    const karyawanNameElem = document.getElementById('karyawanName');

    document.querySelectorAll('.btn-danger[data-bs-toggle="modal"]').forEach(button => {
        button.addEventListener('click', function () {
            const karyawanId = this.getAttribute('data-id');
            const karyawanName = this.getAttribute('data-name');

            karyawanNameElem.textContent = karyawanName;
            deleteForm.action = `/karyawan/${karyawanId}`; // Update form action with dynamic ID
        });
    });

    const successMessage = document.getElementById('successMessage');
    if (successMessage) {
        setTimeout(() => successMessage.style.display = 'none', 5000);
    }
});
</script>
@endsection

@endsection