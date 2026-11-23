<div class="struk">
    <style>
        .struk {
            --ink: #111827;
            --muted: #6b7280;
            --line: #d7dbe0;
            --brand: #4B49AC;
            --accent: #248AFD;
            --gold: #FFC100;
            width: 72mm;
            margin: 0 auto;
            padding: 4mm 3mm;
            background: #fff;
            color: var(--ink);
            font-family: ui-sans-serif, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            font-size: 10.5px;
            line-height: 1.45;
            font-variant-numeric: tabular-nums;
        }

        .struk * { box-sizing: border-box; }

        .struk .brand { text-align: center; }

        .struk .brand .mark {
            display: flex;
            justify-content: center;
            color: var(--brand);
            margin-bottom: .6mm;
        }

        .struk .brand .name {
            font-size: 14px;
            font-weight: 800;
            letter-spacing: .07em;
            text-transform: uppercase;
            color: var(--brand);
        }

        .struk .brand .sub {
            font-size: 9px;
            color: var(--muted);
            letter-spacing: .03em;
        }

        .struk .flag { display: flex; height: 3px; margin: 2mm 0 1.6mm; }
        .struk .flag i { flex: 1; }
        .struk .flag i:nth-child(1) { background: var(--brand); }
        .struk .flag i:nth-child(2) { background: var(--accent); flex: .5; }
        .struk .flag i:nth-child(3) { background: var(--gold); flex: .35; }

        .struk .doc {
            text-align: center;
            font-size: 8.5px;
            font-weight: 700;
            letter-spacing: .24em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .struk dl.meta {
            margin: 1.5mm 0 0;
            display: grid;
            grid-template-columns: auto 1fr;
            gap: .3mm 2mm;
            font-size: 10px;
        }

        .struk dl.meta dt { color: var(--muted); font-weight: 400; }
        .struk dl.meta dd { margin: 0; text-align: right; font-weight: 600; }

        .struk hr { border: 0; border-top: 1px dashed var(--line); margin: 2mm 0; }

        .struk table { width: 100%; border-collapse: collapse; }
        .struk thead th {
            font-size: 8px;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--muted);
            text-align: left;
            padding-bottom: 1.2mm;
        }
        .struk thead th.num, .struk td.num { text-align: right; }
        .struk tbody td { padding: 1.1mm 0; vertical-align: top; }
        .struk tbody tr + tr td { border-top: 1px dotted var(--line); }
        .struk .item { font-weight: 600; }
        .struk .calc { color: var(--muted); font-size: 9.5px; }

        .struk .total {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            border-top: 2px solid var(--ink);
            padding-top: 1.4mm;
            margin-top: .4mm;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: .02em;
        }

        .struk .line {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 3mm;
            font-size: 10px;
            margin-top: .8mm;
        }
        .struk .line span:first-child { color: var(--muted); }

        .struk .badge {
            display: inline-block;
            padding: .2mm 1.8mm;
            border-radius: 1.5mm;
            background: var(--brand);
            color: #fff;
            font-size: 8.5px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .struk .foot { text-align: center; margin-top: 2.4mm; }
        .struk .foot .thanks { font-weight: 700; font-size: 10px; }
        .struk .foot .note { font-size: 8.5px; color: var(--muted); }

        .struk .barcode {
            margin-top: 2mm;
            padding-top: 1.5mm;
            border-top: 1px solid var(--line);
            text-align: center;
        }
        .struk .barcode img { width: 100%; max-height: 14mm; object-fit: contain; }

        @media print {
            /*
             * margin 0 mematikan header/footer bawaan dialog cetak
             * (URL + tanggal) yang dulu ikut tercetak di atas struk.
             */
            @page { size: 80mm auto; margin: 0; }

            html, body { margin: 0; padding: 0; background: #fff; }

            /*
             * @page size:80mm is only a hint: Chrome, driver bawaan, dan
             * "Save as PDF" sering tetap memakai A4/Letter. Jadi lebar struk
             * dikunci di sini, bukan dibiarkan auto.
             */
            body { width: 72mm; margin: 0 auto; }

            .struk {
                width: 72mm;
                padding: 4mm 0;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            /* Border tetap tercetak walau "Background graphics" dimatikan. */
            .struk .flag i { background: none !important; border-top: 3px solid #000; }

            .struk .badge {
                background: none;
                color: #000;
                border: 1px solid #000;
            }

            /* Barcode jangan ikut melebar mengikuti lebar kertas. */
            .struk .barcode img { width: 56mm; height: auto; }
        }
    </style>

    <div class="brand">
        <div class="mark">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 8h16l-1.2 11.2a2 2 0 0 1-2 1.8H7.2a2 2 0 0 1-2-1.8L4 8Z" />
                <path d="M9 8V6a3 3 0 0 1 6 0v2" />
                <path d="m9.4 13.8 1.9 1.9 3.5-3.7" />
            </svg>
        </div>
        <div class="name">{{ $nama_toko }}</div>
        <div class="sub">{{ $situs }}</div>
        @if ($alamat)
            <div class="sub">{{ $alamat }}</div>
        @endif
    </div>

    <div class="flag"><i></i><i></i><i></i></div>
    <div class="doc">Struk Pembayaran</div>

    <dl class="meta">
        <dt>No. transaksi</dt>
        <dd>{{ $order_id }}</dd>
        <dt>Tanggal</dt>
        <dd>{{ $order_date }}</dd>
        <dt>Kasir</dt>
        <dd>{{ $kasir }}</dd>
    </dl>

    <hr>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th class="num">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td>
                        <div class="item">{{ $item['product_name'] }}</div>
                        <div class="calc">{{ $item['quantity'] }} &times; Rp
                            {{ number_format($item['unit_price'], 0, ',', '.') }}</div>
                    </td>
                    <td class="num">Rp {{ number_format($item['total_price'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <hr>

    <div class="total">
        <span>TOTAL</span>
        <span>Rp {{ number_format($total_amount, 0, ',', '.') }}</span>
    </div>

    <div class="line">
        <span>Metode bayar</span>
        <span class="badge">{{ $metode }}</span>
    </div>

    @if ($pegawai)
        <div class="line">
            <span>Potong gaji</span>
            <span>{{ $pegawai }}@if ($periode) &middot; {{ $periode }}@endif</span>
        </div>
    @endif

    <div class="foot">
        <div class="thanks">Terima kasih</div>
        <div class="note">{{ $catatan }}</div>
    </div>

    @if (!empty($barcode))
        <div class="barcode">
            <img src="data:image/png;base64,{{ $barcode }}" alt="Barcode transaksi {{ $order_id }}">
        </div>
    @endif
</div>
