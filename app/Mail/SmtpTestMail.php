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
        /*
         * markdown:, bukan view:.
         *
         * Isi email ini memakai komponen <x-mail::message>, yang hidup di
         * namespace view 'mail' milik Laravel. Namespace itu baru terdaftar
         * ketika perender Markdown dipakai — dengan view: perender itu tidak
         * pernah tersentuh, dan rendernya gagal dengan
         * "No hint path defined for [mail]".
         */
        return new Content(markdown: 'mail.smtp-test');
    }
}
