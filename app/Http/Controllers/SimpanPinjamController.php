<?php

namespace App\Http\Controllers;

use App\Models\Pinjaman;
use App\Models\PinjamanAngsuran;
use App\Models\User;
use App\Services\PinjamanService;
use App\Services\SimpananService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class SimpanPinjamController extends Controller
{
    public function __construct(private readonly PinjamanService $pinjamanService)
    {
    }

    // Daftar pinjaman + form pengajuan. Bila form dikirim dengan tombol Simulasi
    // (GET berisi user_id/pokok/tenor), hasil simulasi ikut ditampilkan.
    public function index(Request $request)
    {
        $pinjaman = Pinjaman::with('user')->latest('id')->get();
        $anggota = $this->anggota();

        $simulasi = null;
        $simulasiError = null;
        if ($request->filled(['user_id', 'pokok', 'tenor'])) {
            $input = $request->validate($this->rules());
            try {
                $simulasi = $this->pinjamanService->simulasi(
                    User::findOrFail($input['user_id']),
                    (float) $input['pokok'],
                    (int) $input['tenor'],
                    $input['metode_bayar'],
                    now(),
                    $request->boolean('kompensasi'),
                );
            } catch (InvalidArgumentException $e) {
                $simulasiError = $e->getMessage();
            }
        }

        return view('contents.simpan-pinjam.index', compact('pinjaman', 'anggota', 'simulasi', 'simulasiError'));
    }

    public function store(Request $request)
    {
        $input = $request->validate($this->rules());

        try {
            $pinjaman = $this->pinjamanService->ajukan(
                User::findOrFail($input['user_id']),
                (float) $input['pokok'],
                (int) $input['tenor'],
                $input['metode_bayar'],
                now(),
                $request->boolean('kompensasi'),
                $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['pokok' => $e->getMessage()])->withInput();
        }

        if ($pinjaman->status === 'ditolak') {
            return redirect()->route('simpan-pinjam.show', $pinjaman)
                ->with('error', 'Pinjaman otomatis ditolak sistem: '.$pinjaman->alasan_penolakan);
        }

        return redirect()->route('simpan-pinjam.show', $pinjaman)->with('success', 'Pinjaman berhasil dibuat.');
    }

    public function show(Pinjaman $pinjaman, SimpananService $simpananService)
    {
        $pinjaman->load('user', 'angsuran', 'dikompensasiOleh');

        return view('contents.simpan-pinjam.show', [
            'pinjaman' => $pinjaman,
            'rincianPelunasan' => $pinjaman->status === 'aktif'
                ? $this->pinjamanService->rincianPelunasan($pinjaman)
                : null,
            'saldoSimpanan' => $simpananService->saldo($pinjaman->user),
        ]);
    }

    public function bayarAngsuran(PinjamanAngsuran $angsuran, Request $request)
    {
        $pinjaman = $this->pinjamanService->bayarAngsuran($angsuran, $request->user());

        $pesan = $pinjaman->status === 'lunas'
            ? 'Angsuran terakhir dibayar, pinjaman lunas.'
            : "Angsuran ke-{$angsuran->bulan_ke} berhasil dibayar.";

        return redirect()->route('simpan-pinjam.show', $pinjaman)->with('success', $pesan);
    }

    public function lunasi(Pinjaman $pinjaman, Request $request)
    {
        $rincian = $this->pinjamanService->lunasiDipercepat($pinjaman, $request->user());

        return redirect()->route('simpan-pinjam.show', $pinjaman)->with(
            'success',
            'Pinjaman lunas. Dibayar Rp'.number_format($rincian->total, 0, ',', '.')
                .' (pokok Rp'.number_format($rincian->sisaPokok, 0, ',', '.')
                .', bunga Rp'.number_format($rincian->bungaDitagih, 0, ',', '.').').'
        );
    }

    // Nota invoice pelunasan, PDF dibuat di server (bukan window.print() seperti struk kasir).
    public function invoice(Pinjaman $pinjaman)
    {
        if ($pinjaman->status !== 'lunas') {
            return redirect()->route('simpan-pinjam.show', $pinjaman)
                ->with('error', 'Invoice pelunasan hanya tersedia untuk pinjaman yang sudah lunas.');
        }

        $pinjaman->load('user', 'angsuran', 'dikompensasiOleh');

        return Pdf::loadView('invoice.pinjaman', ['pinjaman' => $pinjaman])
            ->setPaper('a5', 'portrait')
            ->stream('invoice-'.$pinjaman->nomor().'.pdf');
    }

    public function laporan(Request $request)
    {
        $input = $request->validate(['bulan' => ['nullable', 'date_format:Y-m']]);
        $bulan = $input['bulan'] ?? now()->format('Y-m');

        return view('contents.simpan-pinjam.laporan', $this->pinjamanService->laporanBulanan($bulan));
    }

    private function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,user_id'],
            'pokok' => ['required', 'numeric', 'min:1'],
            'tenor' => [
                'required', 'integer',
                'min:'.(int) config('simpan_pinjam.tenor_min'),
                'max:'.(int) config('simpan_pinjam.tenor_max'),
            ],
            'metode_bayar' => ['required', 'in:potong_gaji,bayar_sendiri'],
            'kompensasi' => ['nullable', 'boolean'],
        ];
    }

    private function anggota()
    {
        return User::where('role', 'karyawan')->orWhereNotNull('jabatan')->orderBy('name')->get();
    }
}
