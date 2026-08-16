<?php

namespace App\Mail;

use App\Models\InvoicePayment;
use App\Services\Billing\ReceiptService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public InvoicePayment $payment,
        protected ReceiptService $receipts,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Kuitansi {$this->payment->receipt_no} — Pembayaran Diterima",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.receipt');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => $this->receipts->pdf($this->payment)->output(),
                $this->receipts->filename($this->payment),
            )->withMime('application/pdf'),
        ];
    }
}
