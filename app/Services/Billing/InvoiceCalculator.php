<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\TariffRate;

/**
 * Menyusun seluruh angka invoice dari pemakaian, tarif, dan setting sistem.
 *
 * Urutan perhitungan:
 *   1. amount_lwbp = kwh_lwbp × rate_lwbp
 *   2. amount_wbp  = kwh_wbp  × rate_wbp
 *   3. biaya_beban = nominal flat, atau kVA × tarif beban per kVA
 *   4. subtotal    = (1) + (2) + (3) + biaya admin
 *   5. PPJ dan PPN dihitung dari subtotal
 *   6. total       = subtotal + PPJ + PPN + penyesuaian, lalu dibulatkan
 *
 * Seluruh tarif dan persentase dikembalikan sebagai bagian dari hasil supaya
 * pemanggilnya menyimpannya sebagai snapshot di baris invoice.
 */
class InvoiceCalculator
{
    /**
     * @param  array{kwh_lwbp:float, kwh_wbp:float, stand_lwbp_start:float, stand_lwbp_end:float, stand_wbp_start:float, stand_wbp_end:float}  $usage
     */
    public function calculate(Customer $customer, ?TariffRate $rate, array $usage, float $adjustment = 0): array
    {
        $rateLwbp = (float) ($rate?->rate_lwbp ?? 0);
        $rateWbp = (float) ($rate?->rate_wbp ?? 0);
        $rateBeban = (float) ($rate?->rate_beban_per_kva ?? 0);

        $amountLwbp = round($usage['kwh_lwbp'] * $rateLwbp, 2);
        $amountWbp = round($usage['kwh_wbp'] * $rateWbp, 2);
        $biayaBeban = round($customer->biayaBebanFor($rate), 2);
        $biayaAdmin = (float) setting('biaya_admin', 0);

        $subtotal = round($amountLwbp + $amountWbp + $biayaBeban + $biayaAdmin, 2);

        $ppjPercent = (float) setting('ppj_percent', 0);
        $ppnPercent = (float) setting('ppn_percent', 0);
        $ppjAmount = round($subtotal * $ppjPercent / 100, 2);
        $ppnAmount = round($subtotal * $ppnPercent / 100, 2);

        $beforeRounding = $subtotal + $ppjAmount + $ppnAmount + $adjustment;
        $total = $this->roundTotal($beforeRounding);

        return [
            'stand_lwbp_start' => $usage['stand_lwbp_start'],
            'stand_lwbp_end' => $usage['stand_lwbp_end'],
            'kwh_lwbp' => $usage['kwh_lwbp'],
            'rate_lwbp' => $rateLwbp,
            'amount_lwbp' => $amountLwbp,

            'stand_wbp_start' => $usage['stand_wbp_start'],
            'stand_wbp_end' => $usage['stand_wbp_end'],
            'kwh_wbp' => $usage['kwh_wbp'],
            'rate_wbp' => $rateWbp,
            'amount_wbp' => $amountWbp,

            'biaya_beban_mode' => $customer->biaya_beban_mode,
            'daya_kva' => (float) $customer->daya_kva,
            'rate_beban_per_kva' => $rateBeban,
            'biaya_beban' => $biayaBeban,
            'biaya_admin' => $biayaAdmin,

            'subtotal' => $subtotal,
            'ppj_percent' => $ppjPercent,
            'ppj_amount' => $ppjAmount,
            'ppn_percent' => $ppnPercent,
            'ppn_amount' => $ppnAmount,
            'rounding' => round($total - $beforeRounding, 2),
            'adjustment' => $adjustment,
            'total_amount' => $total,
        ];
    }

    /**
     * Membulatkan total ke kelipatan terdekat sesuai setting.
     * Nilai 0 berarti tanpa pembulatan.
     */
    private function roundTotal(float $amount): float
    {
        $to = (int) setting('invoice_rounding_to', 0);

        if ($to < 1) {
            return round($amount, 2);
        }

        return (float) (round($amount / $to) * $to);
    }
}
