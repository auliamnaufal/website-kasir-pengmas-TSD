<?php

namespace App\Http\Controllers;

use App\Models\KreditAngsuran;
use App\Models\KreditBarang;
use App\Models\User;
use App\Services\KreditService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use InvalidArgumentException;

class KreditController extends Controller
{
    public function __construct(private readonly KreditService $kreditService)
    {
    }

    // Daftar kredit + form pengajuan. Bila form dikirim dengan tombol Simulasi
    // (GET berisi user_id/nama_barang/pokok/tenor), hasil simulasi ikut ditampilkan.
    public function index(Request $request)
    {
        $kredit = KreditBarang::with('user')->latest('id')->get();
        $anggota = $this->anggota();

        $simulasi = null;
        $simulasiError = null;
        if ($request->filled(['user_id', 'nama_barang', 'pokok', 'tenor'])) {
            $input = $request->validate($this->rules());
            try {
                $simulasi = $this->kreditService->simulasi(
                    User::findOrFail($input['user_id']),
                    (float) $input['pokok'],
                    (int) $input['tenor'],
                    $input['metode_bayar'],
                    now(),
                );
            } catch (InvalidArgumentException $e) {
                $simulasiError = $e->getMessage();
            }
        }

        return view('contents.kredit.index', compact('kredit', 'anggota', 'simulasi', 'simulasiError'));
    }

    public function store(Request $request)
    {
        $input = $request->validate($this->rules());

        try {
            $kredit = $this->kreditService->ajukan(
                User::findOrFail($input['user_id']),
                $input['nama_barang'],
                (float) $input['pokok'],
                (int) $input['tenor'],
                $input['metode_bayar'],
                now(),
                $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['pokok' => $e->getMessage()])->withInput();
        }

        if ($kredit->status === 'ditolak') {
            return redirect()->route('kredit.show', $kredit)
                ->with('error', 'Kredit otomatis ditolak sistem: '.$kredit->alasan_penolakan);
        }

        return redirect()->route('kredit.show', $kredit)->with('success', 'Kredit barang berhasil dibuat.');
    }

    public function show(KreditBarang $kredit)
    {
        $kredit->load('user', 'angsuran');

        return view('contents.kredit.show', [
            'kredit' => $kredit,
            'rincianPelunasan' => $kredit->status === 'disetujui'
                ? $this->kreditService->rincianPelunasan($kredit)
                : null,
        ]);
    }

    public function bayarAngsuran(KreditAngsuran $angsuran, Request $request)
    {
        $kredit = $this->kreditService->bayarAngsuran($angsuran, $request->user());

        $pesan = $kredit->status === 'lunas'
            ? 'Angsuran terakhir dibayar, kredit lunas.'
            : "Angsuran ke-{$angsuran->bulan_ke} berhasil dibayar.";

        return redirect()->route('kredit.show', $kredit)->with('success', $pesan);
    }

    public function lunasi(KreditBarang $kredit, Request $request)
    {
        $rincian = $this->kreditService->lunasiDipercepat($kredit, $request->user());

        return redirect()->route('kredit.show', $kredit)->with(
            'success',
            'Kredit lunas. Dibayar Rp'.number_format($rincian->total, 0, ',', '.')
                .' (pokok Rp'.number_format($rincian->sisaPokok, 0, ',', '.')
                .', bunga Rp'.number_format($rincian->bungaDitagih, 0, ',', '.').').'
        );
    }

    // Nota invoice pelunasan, PDF dibuat di server memakai layout invoice bersama (P2).
    public function invoice(KreditBarang $kredit)
    {
        if ($kredit->status !== 'lunas') {
            return redirect()->route('kredit.show', $kredit)
                ->with('error', 'Invoice pelunasan hanya tersedia untuk kredit yang sudah lunas.');
        }

        $kredit->load('user', 'angsuran');

        return Pdf::loadView('invoice.kredit', ['kredit' => $kredit])
            ->setPaper('a5', 'portrait')
            ->stream('invoice-'.$kredit->nomor().'.pdf');
    }

    public function laporan(Request $request)
    {
        $input = $request->validate(['bulan' => ['nullable', 'date_format:Y-m']]);
        $bulan = $input['bulan'] ?? now()->format('Y-m');

        return view('contents.kredit.laporan', $this->kreditService->laporanBulanan($bulan));
    }

    private function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,user_id'],
            'nama_barang' => ['required', 'string', 'max:255'],
            'pokok' => ['required', 'numeric', 'min:1'],
            'tenor' => [
                'required', 'integer',
                'min:'.(int) config('kredit.tenor_min'),
                'max:'.(int) config('kredit.tenor_max'),
            ],
            'metode_bayar' => ['required', 'in:potong_gaji,bayar_sendiri'],
        ];
    }

    private function anggota()
    {
        return User::where('role', 'karyawan')->orWhereNotNull('jabatan')->orderBy('name')->get();
    }
}
