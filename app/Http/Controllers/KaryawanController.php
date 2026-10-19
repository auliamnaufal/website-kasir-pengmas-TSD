<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\JabatanConfig;

class KaryawanController extends Controller
{
    // Menampilkan daftar karyawan
    public function index()
    {
        // Mengambil semua data karyawan dengan role 'karyawan'
        $karyawans = User::where('role', 'karyawan')->get();

        // Mengirim data ke view
        return view('contents.pegawai', compact('karyawans'));
    }

    // Menampilkan halaman form untuk membuat karyawan baru
    public function create()
    {
        $jabatanConfigs = JabatanConfig::orderBy('level')->get();

        return view('contents.create', compact('jabatanConfigs'));
    }

    // Menyimpan data karyawan baru
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'unique:users',
                'regex:/^[a-zA-Z0-9._%+-]+@gmail\.com$/', // Hanya izinkan email dengan domain @gmail.com
            ],
            'password' => 'required|min:8|confirmed',
            'nik' => 'required|string|max:32|unique:users,nik',
            'jabatan' => 'required|string|exists:jabatan_configs,jabatan',
            'gaji' => 'required|numeric|min:0',
        ], [
            'email.regex' => 'Email harus menggunakan domain @gmail.com',
            'jabatan.exists' => 'Jabatan tidak terdaftar di konfigurasi jabatan.',
        ]);

        // Membuat akun karyawan baru (employee_id digenerate otomatis oleh model)
        User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'password' => bcrypt($validatedData['password']),
            'role' => 'karyawan',
            'nik' => $validatedData['nik'],
            'jabatan' => $validatedData['jabatan'],
            'gaji' => $validatedData['gaji'],
        ]);

        // Redirect ke halaman index dengan pesan sukses
        return redirect()->route('karyawan.index')->with('success', 'Akun karyawan berhasil dibuat.');
    }

    // Menghapus data karyawan
    public function destroy($email)
    {
        // Mencari karyawan berdasarkan ID
        $karyawan = User::where('email', $email)->firstOrFail();

        // Menghapus karyawan
        $karyawan->delete();

        return redirect()->route('karyawan.index')->with('success', 'Karyawan berhasil dihapus.');
    }
}
