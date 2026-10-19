# Analisis & Pembagian Tugas — Tambahan Fitur Kopkar Tirta Suci

> Dokumen analisis requirement dari `Tambahan Fitur Kopkar Tirta Suci.docx`, dipetakan
> terhadap kondisi codebase saat ini. **Status: analisis saja, belum ada implementasi.**

---

## 1. Ringkasan Requirement

Kopkar Tirta Suci punya **2 unit usaha**. Saat ini baru satu yang sudah ada di codebase.

### Unit 1 — Simpan Pinjam (belum ada sama sekali)

| Aturan | Nilai |
|---|---|
| Tenor pinjaman | minimal 10 bulan, maksimal 5 tahun (60 bulan) |
| Pelunasan di bawah 10 bulan | bunga tetap dihitung sampai bulan ke-10 |
| Administrasi (1x per pinjaman) | 3% → 1% masuk administrasi pinjaman, 2% masuk simpanan sukarela |
| Total pelunasan | harus dipisah antara **pokok** dan **bunga** |
| Tambahan kalkulasi | total kompensasi dan pinjam baru |
| Bunga | 1% dari pinjaman |
| Metode bayar | dibedakan **potong gaji** vs **bayar sendiri** |
| Simpanan wajib | berjalan, nominal tergantung jabatan |

Nominal simpanan wajib per jabatan:

| Jabatan | Simpanan Wajib |
|---|---|
| Staff | Rp 100.000 |
| Kasubag | Rp 150.000 |
| Kabag | Rp 200.000 |
| Direksi | Rp 250.000 |

### Unit 2 — Pertokoan (POS sudah ada, kredit barang belum)

Ada 2 bagian:
1. **Pembelian barang di toko** (sembako, kebutuhan pokok) → sudah ada di modul kasir
2. **Kredit Pertokoan** (kredit barang / uang pinjaman) → belum ada

Aturan **Kredit Barang**:

| Aturan | Nilai |
|---|---|
| Plafon pinjaman 1 tahun | maksimal Rp 5.000.000 |
| Tenor | minimal 10 bulan, maksimal 12 bulan |
| Administrasi (1x) | 2% |
| Pelunasan tenor 12 bulan | bunga dihitung sampai bulan ke-11 |
| Bunga | 1% dari pinjaman |

### Note Tambahan yang Diharapkan

1. **Kasir** — tambah 3 opsi pembayaran: 1) Tunai, 2) QRIS, 3) Potong gaji pada bulan berjalan.
2. **Total belanja per ID pegawai** — untuk memantau kecukupan gaji yang dapat dipotong
   pada bulan tersebut. Periode perhitungan: **tgl 16 bulan sebelumnya s/d tgl 15 bulan berikutnya**.
3. **Kode perkiraan (account code)** akan disediakan Konsultan mulai tahun 2027 untuk
   perapian stock barang, diharapkan tersambung ke program kasir. **Saat ini masih dalam
   proses pembuatan → belum dikerjakan, hanya perlu disiapkan slot skemanya.**
4. Fitur tambahan untuk **Simpan Pinjam & Kredit Toko**:
   - a. Sisa plafon pinjaman dalam 1 tahun. Jika sudah lebih dari **50jt → otomatis ditolak sistem**.
   - b. Mengetahui jumlah pelunasan pada bulan tersebut, termasuk **sisa pokok dan bunga sisa**.
   - c. **Cetak nota invoice pelunasan** untuk pinjaman Simpan Pinjam / Kredit barang yang sudah lunas.

---

## 2. Kondisi Codebase Saat Ini

Stack: Laravel 11, Blade, Vite, SQLite (lokal), Bootstrap-based admin template.

Yang **sudah ada**:

- POS / kasir (cari produk, keranjang, proses pembayaran, struk)
- Manajemen produk & kategori, stok, cetak barcode
- Order, order detail, payment
- Laporan harian & laporan bulanan (omset, keuntungan, tren, kategori, produk terlaris)
- Kelola pegawai (buat/hapus akun karyawan)
- Auth + middleware `role` (admin / karyawan)

Model yang ada: `User`, `Category`, `Product`, `Stock`, `Order`, `OrderDetail`, `Payment`.

### Gap Analysis

| # | Gap | Dampak |
|---|-----|--------|
| 1 | `users` hanya punya `name`, `email`, `password`, `role` | **Tidak ada ID pegawai, jabatan, maupun data gaji** — padahal note 2 & simpanan wajib wajib butuh ketiganya |
| 2 | `orders` **tidak punya `user_id`** (di-comment di migrasi) | Mustahil menghitung "total belanja per ID pegawai" |
| 3 | `payment_method` enum = `cash`, `credit_card`, `bank_transfer` | Perlu tambah `qris` & `potong_gaji`. "Potong gaji" bukan pembayaran tunai → harus direpresentasikan sebagai **piutang** |
| 4 | Tidak ada modul simpanan / pinjaman / angsuran / plafon sama sekali | Seluruh Unit 1 + Kredit Pertokoan dibangun dari nol |
| 5 | Cetak struk masih `window.print()` sisi client; route `kasir.cetakstruk` menunjuk `KasirController@cetakStruk` yang **methodnya tidak ada** (route mati) | Untuk nota invoice resmi butuh generate PDF/server-side |
| 6 | Query laporan bulanan pakai fungsi MySQL (`MONTH()`, `YEAR()`, `DAY()`) padahal lokal jalan di SQLite | Perlu dipastikan target DB production |

### Mesin bersama yang dibutuhkan

Semua modul baru (Simpan Pinjam & Kredit Pertokoan) butuh **satu mesin angsuran yang sama**:
jadwal angsuran (pokok / bunga / tenor / biaya administrasi). Ini adalah **titik kopling terbesar**
antar anggota tim, sehingga kontraknya harus dibekukan lebih dulu.

---

## 3. Pembagian Tugas untuk 3 Orang

Prinsip pembagian: **vertical slice per domain** (model + controller + view + route + test),
supaya tiga orang bisa bekerja paralel dengan konflik file minimal.

### P1 — Fondasi Data & Kasir (critical path, dikerjakan paling awal)

Semua anggota lain bergantung ke pekerjaan ini, jadi P1 mengambil bagian paling hulu.

**Tasks:**
1. **Employee master**: tambah `employee_id`/NIK, `jabatan` (Staff / Kasubag / Kabag / Direksi),
   dan data gaji pada `users`. Ini membuka jalan untuk semua fitur lain.
2. **Order → pegawai**: hidupkan `orders.user_id`; kasir wajib memilih pegawai saat transaksi.
3. **3 metode pembayaran** di kasir: Tunai, QRIS, Potong gaji. `potong_gaji` disimpan sebagai
   **piutang** (bukan langsung lunas).
4. **Monitor total belanja per ID pegawai** dengan periode cut-off **16 → 15**, plus validasi
   kecukupan gaji yang dapat dipotong.
5. **Scaffolding kode perkiraan** (kolom kosong untuk 2027 — jangan bikin logikanya dulu).
6. Menetapkan & mendokumentasikan **skema + kontrak** yang akan dipakai P2 dan P3.

**Deliverable:** skema pegawai & order yang stabil, kasir dengan 3 metode bayar, monitor belanja
per pegawai.

**Blocks:** P2, P3 (keduanya butuh master pegawai).

---

### P2 — Unit Simpan Pinjam (modul terbesar, relatif mandiri)

**Tasks:**
1. Mesin angsuran: tenor 10–60 bulan, bunga 1%, administrasi 3% (split 1% pendapatan admin +
   2% simpanan sukarela).
2. Aturan pelunasan: bayar < 10 bulan → bunga tetap dihitung sampai bulan ke-10.
3. Pemisahan **pokok vs bunga** pada total pelunasan.
4. Perhitungan **total kompensasi** dan **pinjam baru**.
5. **Simpanan wajib otomatis per jabatan** + simpanan sukarela.
6. Flag **potong gaji** vs **bayar sendiri**.
7. **Plafon tahunan + auto-reject jika > 50jt**.
8. Laporan pelunasan bulanan (sisa pokok + sisa bunga).
9. **Cetak nota invoice** saat pinjaman lunas.
10. **Membangun mesin angsuran sebagai service reusable** — harus di-freeze lebih dulu karena P3 memakainya.

**Deliverable:** modul simpan pinjam lengkap (simpanan, pinjaman, angsuran, pelunasan, laporan, invoice).

**Blocks:** P3 (butuh mesin angsuran).
**Depends on:** P1 (master pegawai & jabatan).

---

### P3 — Unit Kredit Pertokoan + Reporting & Integrasi

**Tasks:**
1. **Kredit barang**: plafon 5jt/tahun, tenor 10–12 bulan, administrasi 2%, bunga dihitung sampai
   bulan ke-11 (reuse mesin angsuran dari P2).
2. Plafon tahunan + auto-reject untuk kredit barang.
3. Laporan + **nota invoice pelunasan** kredit barang.
4. **Perluasan dashboard & laporan bulanan** agar mencakup omset pinjaman/simpanan/kredit,
   bukan hanya penjualan toko.
5. **QA integrasi end-to-end** + seeder/data dummy untuk semua modul baru.

**Deliverable:** modul kredit pertokoan + laporan gabungan lintas unit + jaminan integrasi.

**Depends on:** P1 (master pegawai) dan P2 (mesin angsuran) — paling rentan menunggu.

---

## 4. Yang Harus Dibekukan Sebelum Kerja Paralel

Jika ini tidak di-lock lebih dulu, P1/P2/P3 akan saling bertabrakan:

| # | Item yang dibekukan | Owner |
|---|---------------------|-------|
| 1 | Skema `users` (employee_id, jabatan, gaji) | P1 |
| 2 | Skema `orders` (user_id) + tabel **piutang / potong gaji** + enum metode bayar | P1 |
| 3 | **Interface mesin angsuran** (input: pokok, bunga, tenor, admin, tgl mulai → output: jadwal angsuran + sisa pokok/bunga) | P2 (freeze di awal, dipakai P3) |
| 4 | Konvensi route/naming + layout nota/invoice bersama (dipakai P2 & P3) | P1 + P2 |
| 5 | Target database (MySQL vs SQLite) — menentukan validasi query laporan | P1 |

**Urutan dependensi:** P1 (pegawai + order linkage) → P2 (mesin angsuran) → P3 (kredit barang).
P3 paling rentan menunggu, jadi P2 sebaiknya menuliskan **kontrak service** di awal walau
implementasinya belum lengkap, supaya P3 bisa jalan paralel.

---

## 5. Pertanyaan yang Perlu Dikonfirmasi ke Klien (Blocking)

Ini ambigu dan mengubah hasil perhitungan secara signifikan:

1. **"Bunga 1%"** — flat 1%/bulan dari pokok pinjaman, atau efektif (menurun mengikuti sisa pokok)?
   Sangat menentukan rumus angsuran.
2. **Pelunasan < 10 bulan** — apakah bunga dibayar penuh seolah tenor 10 bulan (semacam penalti),
   atau tenor dibulatkan ke 10? Berlaku sama untuk aturan "bunga sampai bulan ke-11" pada tenor 12.
3. **Sumber data gaji** — apakah ada nominal gaji per pegawai yang diinput, atau "kecukupan gaji"
   hanya dihitung dari persentase maksimum potongan (mis. maks X% dari gaji)?
4. **Batas potong gaji** — berapa persen maksimum gaji yang boleh dipotong dalam satu bulan?
5. **"Sisa plafon > 50jt auto-reject"** — 50jt itu akumulasi outstanding per anggota, atau per jenis
   pinjaman? Dan window "dalam 1 tahun" itu maksudnya apa persisnya?
6. **Pelunasan potong gaji** — siapa/apa yang menandai piutang potong gaji menjadi lunas
   (proses payroll bulanan, import, atau manual)?
7. **Kode perkiraan 2027** — konfirmasi bahwa ini memang di luar scope sekarang dan cukup
   disiapkan slot skemanya saja.

---

## 6. Catatan Teknis Tambahan (Tech Debt Terkait)

- Route `POST /kasir/cetak-struk` → `KasirController@cetakStruk` **methodnya tidak ada** (route mati).
- `KaryawanController@create` me-render `contents.create` yang **view-nya tidak ada**.
- `Product` model set `$incrementing = false` + generate ID sendiri, tapi migrasi pakai
  `id('product_id')` auto-increment → perlu diselaraskan.
- Migrasi memakai `$table->engine = 'InnoDB'` dan query laporan memakai fungsi MySQL,
  sedangkan environment lokal SQLite → pastikan target DB sebelum menambah laporan baru.
- `Stock` model ada tapi praktis tidak dipakai (stok disimpan langsung di `products.quantity_in_stock`).
