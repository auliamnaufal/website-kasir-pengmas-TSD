<?php

namespace App\Services\Laporan;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class PosUnitLaporan implements UnitLaporan
{
    public function nama(): string
    {
        return 'Toko (Kasir)';
    }

    public function omset(CarbonInterface $dari, CarbonInterface $sampai): float
    {
        return (float) DB::table('payments')
            ->join('orders', 'payments.order_id', '=', 'orders.order_id')
            ->whereBetween('orders.order_date', [$dari->copy()->startOfDay(), $sampai->copy()->endOfDay()])
            ->sum('payments.payment_amount');
    }
}
