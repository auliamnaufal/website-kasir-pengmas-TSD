<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

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
        return view('contents.create');
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
        ], [
            'email.regex' => 'Email harus menggunakan domain @gmail.com',
        ]);

        // Membuat akun karyawan baru
        User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'password' => bcrypt($validatedData['password']),
            'role' => 'karyawan',
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
