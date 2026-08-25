<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Email percobaan dari halaman Setting.
 *
 * Sengaja BUKAN Queueable: gunanya justru memperlihatkan kegagalan SMTP di
 * layar operator sekarang. Kalau di-queue, kegagalannya cuma masuk log worker
 * — persis kondisi yang membuat email aplikasi berhenti terkirim tanpa gejala.
 */
class SmtpTestMail extends Mailable
{
    public function __construct(public string $appName)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Uji Setelan SMTP — {$this->appName}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.smtp-test');
    }
}
