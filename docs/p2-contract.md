# P2 Simpan Pinjam Contract — for P3

Kontrak yang dibekukan oleh P2 (issue #1). Baca ini, bukan source P2, saat
membangun Kredit Pertokoan (P3) atau laporan lintas unit.

## Mesin angsuran — `App\Services\Angsuran\MesinAngsuran`

Service murni: tidak membaca config, tidak menyentuh database, tidak tahu
aturan unit usaha (rentang tenor, plafon, persen administrasi). Setiap unit
memvalidasi aturannya sendiri lalu memanggil mesin ini dengan angka dari
config-nya. Ambil lewat container: `app(MesinAngsuran::class)` atau inject di
constructor.

```php
public function jadwal(
    float $pokok,                     // > 0
    float $bungaPersen,               // persen per bulan, 1 = 1%
    int $tenor,                       // >= 1 bulan
    float $administrasiPersen,        // persen dari pokok, dipotong sekali
    CarbonInterface $tanggalMulai,    // angsuran ke-1 jatuh tempo 1 bulan setelahnya
    string $metodeBunga = MesinAngsuran::FLAT,   // FLAT | EFEKTIF
    ?int $bungaSampaiBulan = null,    // bunga 0 setelah bulan ke-n; null = sepanjang tenor
): JadwalAngsuran

public function pelunasan(
    JadwalAngsuran $jadwal,
    int $angsuranTerbayar,            // 0..tenor
    int $bulanBungaMinimum = 0,
): RincianPelunasan
```

Input tidak valid melempar `InvalidArgumentException`.

### Rumus

- **Pokok per bulan** = `round(pokok / tenor, 2)`. Bulan terakhir menyerap
  selisih pembulatan, jadi `totalPokok()` selalu tepat sama dengan pokok.
- **Bunga `FLAT`** = `pokok awal × bungaPersen%` setiap bulan.
- **Bunga `EFEKTIF`** = `sisa pokok awal bulan × bungaPersen%` (menurun).
- **Administrasi** = `round(pokok × administrasiPersen%, 2)`. Mesin tidak
  memecahnya; P2 memecah 1% pendapatan + 2% simpanan sukarela di `PinjamanService`.
- **Jatuh tempo** bulan ke-n = `tanggalMulai + n bulan` tanpa overflow akhir
  bulan (31 Jan → 28 Feb → 31 Mar).
- **Pelunasan dipercepat** setelah `k` angsuran terbayar: sisa pokok seluruhnya
  + bunga bulan `k+1` sampai `max(k+1, bulanBungaMinimum)` (dibatasi tenor).
  Bunga bulan setelahnya dibebaskan.

Untuk P3 kredit barang, aturan "tenor 12, bunga sampai bulan ke-11" bisa
diwakili `bungaSampaiBulan: 11` (bunga bulan ke-12 = 0). Apakah itu tafsiran
yang benar masih open question klien (#2), tapi mesinnya sudah mendukung.

### Hasil (immutable, semua nominal rupiah 2 desimal)

`JadwalAngsuran`: `pokok`, `bungaPersen`, `tenor`, `metodeBunga`,
`administrasi`, `tanggalMulai` (CarbonImmutable), `baris` (list
`BarisAngsuran`), dan `totalPokok()`, `totalBunga()`, `totalAngsuran()`,
`pokokBersih()` (pokok − administrasi), `sisaPokokSetelah(k)`,
`sisaBungaSetelah(k)`, `bulan(n)`, `toArray()`.

`BarisAngsuran`: `bulanKe`, `jatuhTempo`, `pokok`, `bunga`, `total`,
`sisaPokok` dan `sisaBunga` (keduanya SETELAH angsuran bulan itu dibayar).

`RincianPelunasan`: `angsuranTerbayar`, `bungaSampaiBulan`, `sisaPokok`,
`bungaDitagih`, `bungaDibebaskan`, `total` (= sisaPokok + bungaDitagih).
Pokok dan bunga sengaja dipisah.

Simpan baris jadwal ke tabel angsuran unit masing-masing (P3: `kredit_angsuran`
sudah punya kolom `bulan_ke`, `jatuh_tempo`, `pokok`, `bunga`). Untuk menghitung
pelunasan pinjaman tersimpan, panggil ulang `jadwal()` dengan parameter yang
disimpan saat pengajuan lalu `pelunasan()` — contoh:
`PinjamanService::rincianPelunasan()`.

## Tabel P2

| Tabel | Isi |
|---|---|
| `pinjaman` | Satu baris per pengajuan. `status`: `aktif`, `ditolak` (auto-reject, tanpa jadwal), `lunas`. Menyimpan parameter yang dibekukan (`bunga_persen`, `metode_bunga`, `bulan_bunga_minimum`), pecahan administrasi, `kompensasi`, `dana_diterima`, `metode_bayar` (`potong_gaji` / `bayar_sendiri`), dan saat lunas `pelunasan_pokok` + `pelunasan_bunga`. |
| `pinjaman_angsuran` | Satu baris per bulan. `status`: `belum_lunas`, `lunas`, `pelunasan` (ditutup pelunasan dipercepat / kompensasi). |
| `simpanan` | Buku setoran. `jenis`: `wajib` / `sukarela`. `periode` "YYYY-MM" mengikuti `Piutang::periodFor()`. `sumber_type = 'pinjaman'` untuk 2% administrasi. |

Semua nominal `decimal(15,2)`. `kode_perkiraan` nullable di `pinjaman` dan
`pinjaman_angsuran` sebagai slot Konsultan 2027, sama seperti P3.

## Integrasi dengan piutang P1

Pinjaman `potong_gaji` menulis satu baris `piutang` per angsuran:
`source_type = 'pinjaman'`, `source_id = pinjaman_angsuran.id`,
`period = Piutang::periodFor(jatuh_tempo)`. Akibatnya cicilan pinjaman ikut
mengurangi sisa batas potong gaji untuk belanja di kasir.

- Bayar angsuran (`PinjamanService::bayarAngsuran`) ikut menandai piutangnya lunas.
- Tandai lunas piutang `pinjaman` di `PiutangController` diteruskan ke
  `bayarAngsuran`, jadi urutan angsuran tetap dijaga.
- Pelunasan dipercepat / kompensasi menghapus piutang angsuran yang belum
  jatuh tempo (tidak akan dipotong lagi).

P3 bisa mengikuti pola yang sama dengan `source_type = 'kredit'`.

## Layout invoice bersama

`resources/views/invoice/layout.blade.php` (kop dari `config/invoice.php`).
View turunan mengisi section `judul`, `nomor`, `isi`, lalu dirender server-side:
`Pdf::loadView('invoice.xxx', [...])->stream()` (`barryvdh/laravel-dompdf`).
Contoh: `resources/views/invoice/pinjaman.blade.php`.

## Asumsi open question (semua di `config/simpan_pinjam.php`, bisa diubah lewat .env)

| # | Pertanyaan | Default sekarang | Config |
|---|---|---|---|
| 1 | Bunga flat atau efektif | `flat` | `SP_METODE_BUNGA` |
| 2 | Pelunasan < 10 bulan | bunga ditagih sampai bulan ke-10, sisanya dibebaskan | `SP_BULAN_BUNGA_MINIMUM` |
| 3 | Plafon Rp50 jt | sisa pokok semua pinjaman aktif anggota (`outstanding`); alternatif `tahunan` = total pokok di tahun kalender seperti P3 | `SP_PLAFON`, `SP_PLAFON_BASIS` |
| 4 | Siapa menandai potong gaji lunas | admin, manual (bayar angsuran atau tandai lunas piutang) | — |

Plafon simpan pinjam dan plafon kredit barang P3 masih dihitung terpisah.
