<?php

namespace App\Http\Controllers;

use App\Models\JabatanConfig;
use Illuminate\Http\Request;

class JabatanConfigController extends Controller
{
    public function index()
    {
        $jabatanConfigs = JabatanConfig::orderBy('level')->get();

        return view('contents.jabatan-config', compact('jabatanConfigs'));
    }

    public function update(Request $request, JabatanConfig $jabatanConfig)
    {
        $validated = $request->validate([
            'level' => 'required|integer|min:1',
            'simpanan_wajib' => 'required|numeric|min:0',
            'max_potongan_persen' => 'required|numeric|min:0|max:100',
        ]);

        $jabatanConfig->update($validated);

        return redirect()->route('jabatan-config.index')->with('success', 'Konfigurasi jabatan berhasil diubah.');
    }
}
