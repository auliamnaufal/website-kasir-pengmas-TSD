<?php

namespace App\Http\Controllers;

use App\Models\Piutang;
use Illuminate\Validation\ValidationException;

class PiutangController extends Controller
{
    // Tandai piutang sebagai lunas (admin only — lihat middleware role:admin di routes/web.php).
    // Piutang yang sudah lunas tidak bisa ditandai ulang: settled_at/settled_by adalah jejak
    // audit siapa & kapan pertama kali melunasi (spec #3 story 18), jadi direject (422)
    // alih-alih ditimpa diam-diam oleh klik kedua.
    public function tandaiLunas(Piutang $piutang)
    {
        if ($piutang->status === 'lunas') {
            throw ValidationException::withMessages([
                'status' => 'Piutang ini sudah ditandai lunas sebelumnya.',
            ]);
        }

        $piutang->update([
            'status' => 'lunas',
            'settled_at' => now(),
            'settled_by' => auth()->id(),
        ]);

        return redirect()->back()->with('success', 'Piutang berhasil ditandai lunas.');
    }
}
