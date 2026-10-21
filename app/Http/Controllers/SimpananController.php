<?php

namespace App\Http\Controllers;

use App\Models\Piutang;
use App\Models\Simpanan;
use App\Models\User;
use App\Services\SimpananService;
use Illuminate\Http\Request;

class SimpananController extends Controller
{
    public function __construct(private readonly SimpananService $simpananService)
    {
    }

    public function index()
    {
        $saldo = Simpanan::selectRaw('user_id, jenis, SUM(jumlah) as total')
            ->groupBy('user_id', 'jenis')
            ->get()
            ->groupBy('user_id');

        $anggota = User::where('role', 'karyawan')->orWhereNotNull('jabatan')->orderBy('name')->get()
            ->map(function (User $user) use ($saldo) {
                $baris = $saldo->get($user->user_id, collect());
                $wajib = (float) ($baris->firstWhere('jenis', 'wajib')->total ?? 0);
                $sukarela = (float) ($baris->firstWhere('jenis', 'sukarela')->total ?? 0);

                return ['user' => $user, 'wajib' => $wajib, 'sukarela' => $sukarela, 'total' => $wajib + $sukarela];
            });

        return view('contents.simpanan', [
            'anggota' => $anggota,
            'riwayat' => Simpanan::with('user')->latest('id')->limit(50)->get(),
            'periodeSekarang' => Piutang::periodFor(now()),
        ]);
    }

    public function generateWajib(Request $request)
    {
        $input = $request->validate(['periode' => ['required', 'date_format:Y-m']]);

        $dibuat = $this->simpananService->generateWajib($input['periode'], $request->user());

        return redirect()->route('simpanan.index')
            ->with('success', "{$dibuat} simpanan wajib dicatat untuk periode {$input['periode']}.");
    }

    public function storeSukarela(Request $request)
    {
        $input = $request->validate([
            'user_id' => ['required', 'exists:users,user_id'],
            'jumlah' => ['required', 'numeric', 'min:1'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $this->simpananService->setorSukarela(
            User::findOrFail($input['user_id']),
            (float) $input['jumlah'],
            now(),
            $input['keterangan'] ?? null,
            $request->user(),
        );

        return redirect()->route('simpanan.index')->with('success', 'Simpanan sukarela berhasil dicatat.');
    }
}
