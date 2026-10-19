@extends('layouts.app')

@section('content')
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header bg-primary text-white text-center">
                    <h4 class="mb-0">Konfigurasi Jabatan</h4>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th>Jabatan</th>
                                    <th>Level</th>
                                    <th>Simpanan Wajib</th>
                                    <th>Maks. Potongan Gaji (%)</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($jabatanConfigs as $config)
                                    @php($formId = 'jabatanForm' . $config->id)
                                    <tr>
                                        <td>{{ $config->jabatan }}</td>
                                        <td>
                                            <input type="number" form="{{ $formId }}" name="level" class="form-control" value="{{ $config->level }}" min="1" required>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" form="{{ $formId }}" name="simpanan_wajib" class="form-control" value="{{ $config->simpanan_wajib }}" min="0" required>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" form="{{ $formId }}" name="max_potongan_persen" class="form-control" value="{{ $config->max_potongan_persen }}" min="0" max="100" required>
                                        </td>
                                        <td>
                                            <form id="{{ $formId }}" action="{{ route('jabatan-config.update', $config) }}" method="POST">
                                                @csrf
                                                @method('PUT')
                                                <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
