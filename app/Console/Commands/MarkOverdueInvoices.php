<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

class MarkOverdueInvoices extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Menandai invoice terbit yang sudah lewat jatuh tempo';

    public function handle(): int
    {
        // Hanya invoice yang benar-benar sudah terbit; draft belum ditagihkan
        // dan invoice partial sudah punya statusnya sendiri.
        $count = Invoice::where('status', 'issued')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);

        $this->info("{$count} invoice ditandai jatuh tempo.");

        return self::SUCCESS;
    }
}
