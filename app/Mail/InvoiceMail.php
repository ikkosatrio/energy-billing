<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Services\Billing\InvoiceDocumentService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        protected InvoiceDocumentService $documents,
    ) {
    }

    public function envelope(): Envelope
    {
        $period = $this->invoice->period_start->translatedFormat('F Y');

        return new Envelope(
            subject: "Invoice {$this->invoice->invoice_no} — Pemakaian Listrik {$period}",
        );
    }

    public function content(): Content
    {
        /*
         * markdown:, bukan view:.
         *
         * Isi email ini memakai komponen <x-mail::message>, yang hidup di
         * namespace view 'mail' milik Laravel. Namespace itu baru terdaftar
         * ketika perender Markdown dipakai — dengan view: perender itu tidak
         * pernah tersentuh, dan rendernya gagal dengan
         * "No hint path defined for [mail]".
         */
        return new Content(markdown: 'mail.invoice');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => $this->documents->pdf($this->invoice)->output(),
                $this->documents->filename($this->invoice),
            )->withMime('application/pdf'),
        ];
    }
}
