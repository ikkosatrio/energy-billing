<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Pemberitahuan bahwa kuitansi yang pernah dikirim dibatalkan.
 *
 * Datanya dilewatkan sebagai nilai, bukan model: pembayarannya sudah dihapus
 * saat email ini terkirim dari antrean, sehingga SerializesModels tidak akan
 * menemukan barisnya lagi.
 */
class ReceiptVoidedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ?string $receiptNo,
        public ?string $invoiceNo,
        public ?string $customerName,
        public float $amount,
        public ?string $reason = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Pembatalan Kuitansi {$this->receiptNo}",
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
        return new Content(markdown: 'mail.receipt-voided');
    }
}
