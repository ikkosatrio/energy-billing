<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PaymentBatch;
use App\Services\ActivityLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Impor pembayaran dari berkas, untuk menggantikan pencatatan satu per satu
 * saat jumlah pelanggan sudah banyak.
 *
 * Alurnya dua langkah dan sengaja tidak disatukan: berkas dibaca dan diperiksa
 * lebih dulu (preview), baru disimpan setelah operator menyetujui (commit).
 * Menyimpan langsung saat unggah berarti kesalahan ketik pada satu baris sudah
 * terlanjur menjadi pembayaran sungguhan sebelum siapa pun sempat melihatnya.
 *
 * Nomor invoice WAJIB ada di tiap baris. Mencocokkan lewat nama pelanggan dan
 * nominal terdengar praktis, tapi dua pelanggan dengan tagihan sama besar akan
 * membuat uang tercatat di tagihan orang lain — kesalahan yang baru ketahuan
 * saat pelanggan protes. Nama pelanggan tetap diminta, tapi hanya sebagai
 * pemeriksa silang.
 */
class PaymentImportService
{
    /** Judul kolom berkas, harus persis urutannya. */
    public const COLUMNS = [
        'tanggal_bayar',
        'no_invoice',
        'nama_pelanggan',
        'jumlah',
        'metode',
        'no_referensi',
        'catatan',
    ];

    /** Batas baris per berkas. */
    public const MAX_ROWS = 500;

    private const METHODS = ['transfer', 'cash', 'other'];

    /**
     * Membaca berkas dan memeriksa tiap baris tanpa menyimpan apa pun.
     *
     * @return array{rows: array<int, array<string, mixed>>, valid: int, invalid: int, total: float}
     */
    public function preview(string $path): array
    {
        $sheets = Excel::toArray([], $path);
        $raw = $sheets[0] ?? [];

        if (empty($raw)) {
            return ['rows' => [], 'valid' => 0, 'invalid' => 0, 'total' => 0.0];
        }

        // Baris pertama adalah judul kolom.
        array_shift($raw);

        $rows = [];
        $seen = [];
        $lineNumber = 1;

        foreach ($raw as $line) {
            $lineNumber++;

            if ($this->isBlank($line)) {
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                break;
            }

            $rows[] = $this->examine($line, $lineNumber, $seen);
        }

        $valid = array_values(array_filter($rows, fn ($row) => $row['ok']));

        return [
            'rows' => $rows,
            'valid' => count($valid),
            'invalid' => count($rows) - count($valid),
            'total' => array_sum(array_column($valid, 'amount')),
        ];
    }

    /**
     * Menyimpan baris yang lolos pemeriksaan sebagai satu batch.
     *
     * @param  array<int, array<string, mixed>>  $rows  hasil preview()
     * @return array{batch: ?PaymentBatch, created: int, total: float, failed: array<int, string>}
     */
    public function commit(array $rows, string $filename): array
    {
        $eligible = array_values(array_filter($rows, fn ($row) => $row['ok']));

        if (empty($eligible)) {
            return ['batch' => null, 'created' => 0, 'total' => 0.0, 'failed' => []];
        }

        $failed = [];

        $batch = DB::transaction(function () use ($eligible, $filename, &$failed) {
            $batch = PaymentBatch::create([
                'type' => 'import',
                'source' => $filename,
                'created_by' => auth()->id(),
            ]);

            $created = 0;
            $total = 0.0;

            foreach ($eligible as $row) {
                try {
                    InvoicePayment::create([
                        'invoice_id' => $row['invoice_id'],
                        'payment_date' => $row['payment_date'],
                        'amount' => $row['amount'],
                        'method' => $row['method'],
                        'reference_no' => $row['reference_no'],
                        'notes' => $row['notes'],
                        'recorded_by' => auth()->id(),
                        'payment_batch_id' => $batch->id,
                        'import_hash' => InvoicePayment::importHash(
                            $row['invoice_id'],
                            $row['payment_date'],
                            $row['amount'],
                            $row['reference_no'],
                        ),
                    ]);

                    $created++;
                    $total += $row['amount'];
                } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                    // Unique index import_hash menolaknya: baris ini sudah
                    // pernah masuk lewat berkas sebelumnya.
                    $failed[] = "Baris {$row['line']}: pembayaran ini sudah pernah diimpor.";
                }
            }

            $batch->update(['payment_count' => $created, 'total_amount' => $total]);

            return $batch;
        });

        ActivityLogger::log(
            'import_payment',
            $batch,
            "Impor {$batch->payment_count} pembayaran dari {$filename} sebesar ".rupiah($batch->total_amount),
        );

        return [
            'batch' => $batch,
            'created' => $batch->payment_count,
            'total' => (float) $batch->total_amount,
            'failed' => $failed,
        ];
    }

    /**
     * Memeriksa satu baris dan mengembalikan bentuk siap tampil.
     *
     * @param  array<int, mixed>  $line
     * @param  array<string, int>  $seen  sidik jari baris yang sudah diperiksa
     * @return array<string, mixed>
     */
    private function examine(array $line, int $lineNumber, array &$seen): array
    {
        [$rawDate, $invoiceNo, $customerName, $rawAmount, $method, $reference, $notes] =
            array_pad(array_slice($line, 0, 7), 7, null);

        $row = [
            'line' => $lineNumber,
            'invoice_no' => trim((string) $invoiceNo),
            'customer_name' => trim((string) $customerName),
            'payment_date' => null,
            'amount' => 0.0,
            'method' => strtolower(trim((string) $method)) ?: 'transfer',
            'reference_no' => trim((string) $reference) ?: null,
            'notes' => trim((string) $notes) ?: null,
            'invoice_id' => null,
            'ok' => false,
            'error' => null,
        ];

        if ($row['invoice_no'] === '') {
            return ['error' => 'Nomor invoice wajib diisi.'] + $row;
        }

        $date = $this->parseDate($rawDate);

        if (!$date) {
            return ['error' => 'Tanggal bayar tidak terbaca, gunakan format YYYY-MM-DD.'] + $row;
        }

        $row['payment_date'] = $date;

        $amount = $this->parseAmount($rawAmount);

        if ($amount === null || $amount <= 0) {
            return ['error' => 'Jumlah harus berupa angka lebih dari nol.'] + $row;
        }

        $row['amount'] = $amount;

        if (!in_array($row['method'], self::METHODS, true)) {
            return ['error' => 'Metode harus transfer, cash, atau other.'] + $row;
        }

        $invoice = Invoice::where('invoice_no', $row['invoice_no'])->first();

        if (!$invoice) {
            return ['error' => 'Nomor invoice tidak ditemukan.'] + $row;
        }

        $row['invoice_id'] = $invoice->id;
        $row['outstanding'] = $invoice->outstanding;

        if ($invoice->status === 'draft') {
            return ['error' => 'Invoice masih draft, terbitkan dulu.'] + $row;
        }

        if ($invoice->isCancelled()) {
            return ['error' => 'Invoice sudah dibatalkan.'] + $row;
        }

        // Pemeriksa silang, bukan alat pencocokan: nama yang tidak cocok
        // hampir selalu berarti baris tertukar saat menyusun berkas.
        if ($row['customer_name'] !== '' && !$this->nameMatches($row['customer_name'], $invoice->customer_name)) {
            return ['error' => "Nama pelanggan tidak cocok — invoice ini milik {$invoice->customer_name}."] + $row;
        }

        // Pemeriksaan duplikat didahulukan dari pemeriksaan sisa tagihan.
        // Berkas yang diimpor ulang membuat tagihannya sudah lunas, sehingga
        // kalau urutannya dibalik barisnya ditolak sebagai "melebihi sisa
        // tagihan" — benar hasilnya, tapi operator jadi mengira nominalnya
        // yang salah dan mengubah berkas, bukan berhenti mengunggah ulang.
        $hash = InvoicePayment::importHash($invoice->id, $date, $amount, $row['reference_no']);

        if (isset($seen[$hash])) {
            return ['error' => "Sama persis dengan baris {$seen[$hash]} di berkas ini."] + $row;
        }

        $seen[$hash] = $lineNumber;

        if (InvoicePayment::where('import_hash', $hash)->exists()) {
            return ['error' => 'Pembayaran ini sudah pernah diimpor sebelumnya.'] + $row;
        }

        if ($amount > $invoice->outstanding + 0.5) {
            return ['error' => 'Jumlah melebihi sisa tagihan '.rupiah($invoice->outstanding).'.'] + $row;
        }

        $row['ok'] = true;

        return $row;
    }

    /** @param array<int, mixed> $line */
    private function isBlank(array $line): bool
    {
        foreach ($line as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Excel menyimpan tanggal sebagai angka serial, sementara berkas CSV hasil
     * pivot mengirim teks. Keduanya harus diterima.
     */
    private function parseDate(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return Carbon::instance(
                    \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value),
                )->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return Carbon::parse(trim((string) $value))->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Menerima "4500000", "4.500.000", dan "4500000,00" — tiga bentuk yang
     * sama-sama lazim keluar dari export mutasi bank Indonesia.
     */
    private function parseAmount(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $clean = trim((string) $value);

        if ($clean === '') {
            return null;
        }

        $clean = str_replace(['.', ' ', 'Rp', 'rp'], '', $clean);
        $clean = str_replace(',', '.', $clean);

        return is_numeric($clean) ? (float) $clean : null;
    }

    /**
     * Perbandingan longgar: berkas hasil pivot kerap memuat nama dengan spasi
     * ganda atau tanpa bentuk badan usaha, dan itu bukan alasan menolak baris.
     */
    private function nameMatches(string $given, ?string $actual): bool
    {
        $normalise = fn (?string $value) => preg_replace('/[^a-z0-9]/', '', strtolower((string) $value));

        $a = $normalise($given);
        $b = $normalise($actual);

        return $a === $b || str_contains($b, $a) || str_contains($a, $b);
    }
}
