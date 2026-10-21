{{--
    Layout nota invoice bersama untuk PDF (dompdf), dipakai Simpan Pinjam (P2)
    dan Kredit Pertokoan (P3). View turunan mengisi section `judul`, `nomor`,
    dan `isi`. dompdf hanya mendukung CSS sederhana: pakai tabel, bukan flex/grid.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>@yield('judul') @yield('nomor')</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
        .kop { width: 100%; border-bottom: 2px solid #222; padding-bottom: 6px; margin-bottom: 10px; }
        .kop .nama { font-size: 14px; font-weight: bold; }
        .judul { font-size: 13px; font-weight: bold; text-align: center; margin: 8px 0 2px; letter-spacing: 1px; }
        .nomor { text-align: center; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        .info td { padding: 2px 0; vertical-align: top; }
        .rincian { margin-top: 8px; }
        .rincian th, .rincian td { border: 1px solid #999; padding: 4px; }
        .rincian th { background: #eee; text-align: left; }
        .kanan { text-align: right; }
        .total td { font-weight: bold; }
        .cap { margin-top: 14px; padding: 6px; border: 2px solid #1a7f37; color: #1a7f37; text-align: center; font-weight: bold; font-size: 12px; }
        .ttd { margin-top: 28px; }
        .ttd td { text-align: center; width: 50%; }
        .kecil { font-size: 8px; color: #666; }
    </style>
</head>
<body>
    <table class="kop">
        <tr>
            <td>
                <div class="nama">{{ config('invoice.nama') }}</div>
                @if (config('invoice.alamat'))
                    <div>{{ config('invoice.alamat') }}</div>
                @endif
            </td>
            <td class="kanan kecil">Dicetak {{ now()->format('d/m/Y H:i') }}</td>
        </tr>
    </table>

    <div class="judul">@yield('judul')</div>
    <div class="nomor">No. @yield('nomor')</div>

    @yield('isi')
</body>
</html>
