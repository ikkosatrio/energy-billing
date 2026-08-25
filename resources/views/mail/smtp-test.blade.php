<x-mail::message>
# Setelan SMTP Berhasil

Email ini dikirim dari halaman **Setting Aplikasi** {{ $appName }} untuk menguji
konfigurasi SMTP.

Kalau email ini sampai ke kotak masuk Anda, berarti host, port, kredensial, dan
alamat pengirimnya sudah benar — invoice dan kuitansi akan terkirim lewat jalur
yang sama.

<x-mail::panel>
Email aplikasi yang sebenarnya dikirim lewat antrean, jadi pastikan container
<code>queue</code> juga berjalan. Email uji ini dikirim langsung, tanpa antrean.
</x-mail::panel>

Salam,<br>
{{ setting('company_name', config('app.name')) }}
</x-mail::message>
