@extends('layouts.app')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">

            <!-- Daftar Akun Karyawan -->
            <div class="card mt-4">
                <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Daftar Akun Karyawan</h5>
                    <a href="{{ route('karyawan.create') }}" class="btn btn-primary btn-sm">Tambah Karyawan</a>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div id="successMessage" class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if($karyawans->isEmpty())
                        <p class="text-center">Belum ada akun karyawan yang dibuat.</p>
                    @else
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead class="bg-light">
                                <tr>
                                    <th>No</th>
                                    <th>Employee ID</th>
                                    <th>Nama</th>
                                    <th>Email</th>
                                    <th>NIK</th>
                                    <th>Jabatan</th>
                                    <th>Gaji</th>
                                    <th>Dibuat Pada</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($karyawans as $index => $karyawan)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $karyawan->employee_id ?? '-' }}</td>
                                        <td>{{ $karyawan->name }}</td>
                                        <td>{{ $karyawan->email }}</td>
                                        <td>{{ $karyawan->nik ?? '-' }}</td>
                                        <td>{{ $karyawan->jabatan ?? '-' }}</td>
                                        <td>{{ $karyawan->gaji !== null ? number_format($karyawan->gaji, 0) : '-' }}</td>
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