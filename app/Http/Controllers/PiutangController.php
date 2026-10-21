<?php

namespace App\Http\Controllers;

use App\Models\KreditAngsuran;
use App\Models\PinjamanAngsuran;
use App\Models\Piutang;
use App\Services\KreditService;
use App\Services\PinjamanService;
use Illuminate\Validation\ValidationException;

class PiutangController extends Controller
{
    // Tandai piutang sebagai lunas (admin only — lihat middleware role:admin di routes/web.php).
    // Piutang yang sudah lunas tidak bisa ditandai ulang: settled_at/settled_by adalah jejak
    // audit siapa & kapan pertama kali melunasi (spec #3 story 18), jadi direject (422)
    // alih-alih ditimpa diam-diam oleh klik kedua.
    public function tandaiLunas(Piutang $piutang, PinjamanService $pinjamanService, KreditService $kreditService)
    {
        if ($piutang->status === 'lunas') {
            throw ValidationException::withMessages([
                'status' => 'Piutang ini sudah ditandai lunas sebelumnya.',
            ]);
        }

        // Piutang angsuran pinjaman (P2): lunasi lewat PinjamanService supaya angsuran
        // dan status pinjamannya ikut berubah, dan urutan angsuran tetap dijaga.
        if ($piutang->source_type === 'pinjaman') {
            $angsuran = PinjamanAngsuran::findOrFail($piutang->source_id);
            $pinjamanService->bayarAngsuran($angsuran, auth()->user());

            return redirect()->back()->with('success', 'Piutang dan angsuran pinjaman berhasil ditandai lunas.');
        }

        // Piutang angsuran kredit barang (P3): sama, lewat KreditService.
        if ($piutang->source_type === 'kredit') {
            $angsuran = KreditAngsuran::findOrFail($piutang->source_id);
            $kreditService->bayarAngsuran($angsuran, auth()->user());

            return redirect()->back()->with('success', 'Piutang dan angsuran kredit berhasil ditandai lunas.');
        }

        $piutang->update([
            'status' => 'lunas',
            'settled_at' => now(),
            'settled_by' => auth()->id(),
        ]);

        return redirect()->back()->with('success', 'Piutang berhasil ditandai lunas.');
    }
}
