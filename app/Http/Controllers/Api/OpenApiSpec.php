<?php

namespace App\Http\Controllers\Api;

use OpenApi\Attributes as OA;

/**
 * Metadata dan schema bersama untuk dokumentasi OpenAPI.
 *
 * Kelas ini tidak pernah dijalankan — hanya dibaca swagger-php saat
 * `php artisan l5-swagger:generate`. Dipisah dari controller supaya
 * definisi schema tidak menenggelamkan kode yang benar-benar berjalan.
 */
#[OA\Info(
    version: '1.0.0',
    title: 'Energy Billing API',
    description: <<<'TXT'
    API untuk gateway IoT mengirim pembacaan power meter ke aplikasi Energy Billing.

    ## Identifikasi meter

    Setiap permintaan menyebut meter lewat `meter_id` — ID numerik yang tampil di
    halaman **Power Meter Device** dan bisa dilihat kapan saja. Daftar lengkapnya
    juga tersedia lewat `GET /api/v1/meters`.

    ## Autentikasi

    Seluruh endpoint memakai satu API token global yang sama untuk semua gateway.
    Kirim lewat header:

    ```
    X-Api-Token: <token>
    ```

    Token diatur di menu **Setting Aplikasi → Integrasi IoT**, bisa dilihat,
    disalin, dan digenerate ulang kapan saja. Bila token di setting dikosongkan,
    autentikasi dimatikan — hanya lakukan itu bila server benar-benar tertutup di
    jaringan internal, karena data yang masuk endpoint ini langsung menentukan
    nominal tagihan pelanggan.
    TXT,
)]
/*
 * Pilihan server pada dropdown Swagger UI.
 *
 * Entri pertama relatif ('/') sehingga "Try it out" selalu menembak host yang
 * sedang membuka halaman ini — benar di local, staging, maupun produksi tanpa
 * konfigurasi apa pun.
 *
 * Dua entri berikutnya untuk menembak lingkungan lain dari satu halaman docs.
 * URL-nya diambil dari konstanta yang didefinisikan di config/l5-swagger.php
 * (lihat kunci `constants`), diisi lewat .env:
 *   SWAGGER_SERVER_STAGING, SWAGGER_SERVER_PRODUCTION
 */
#[OA\Server(url: '/', description: 'Server saat ini (mengikuti host halaman ini)')]
#[OA\Server(url: L5_SWAGGER_SERVER_STAGING, description: 'Staging')]
#[OA\Server(url: L5_SWAGGER_SERVER_PRODUCTION, description: 'Produksi')]
#[OA\SecurityScheme(
    securityScheme: 'ApiToken',
    type: 'apiKey',
    name: 'X-Api-Token',
    in: 'header',
    description: 'API token global dari menu Setting Aplikasi → Integrasi IoT.',
)]

// ── Schema payload ───────────────────────────────────────────────────────

#[OA\Schema(
    schema: 'ReadingItem',
    title: 'Reading',
    description: 'Satu pembacaan meter pada satu titik waktu.',
    required: ['read_at', 'stand_lwbp', 'stand_wbp'],
    properties: [
        new OA\Property(property: 'read_at', type: 'string', format: 'date-time',
            description: 'Waktu pembacaan di sisi meter. Dua format diterima: ISO 8601 dengan offset '
                .'(2026-08-13T10:35:00+07:00) atau "YYYY-MM-DD HH:MM:SS" (2026-08-13 10:35:00). '
                .'Tanpa offset, waktu dibaca sebagai WIB (Asia/Jakarta) — bila jam perangkat memakai UTC, '
                .'gunakan format ber-offset agar tidak tercatat mundur 7 jam.',
            example: '2026-08-13T10:35:00+07:00'),
        new OA\Property(property: 'stand_lwbp', type: 'number', format: 'float',
            description: 'Stand kumulatif register LWBP (kWh), bukan pemakaian per interval.',
            example: 1270280.5),
        new OA\Property(property: 'stand_wbp', type: 'number', format: 'float',
            description: 'Stand kumulatif register WBP (kWh).',
            example: 414260.2),
        new OA\Property(property: 'active_power_kw', type: 'number', format: 'float', nullable: true,
            description: 'Daya aktif sesaat, dipakai halaman real-time dan beban puncak.', example: 412.6),
        new OA\Property(property: 'voltage_r', type: 'number', format: 'float', nullable: true, example: 380.1),
        new OA\Property(property: 'voltage_s', type: 'number', format: 'float', nullable: true, example: 379.8),
        new OA\Property(property: 'voltage_t', type: 'number', format: 'float', nullable: true, example: 380.4),
        new OA\Property(property: 'current_r', type: 'number', format: 'float', nullable: true, example: 410.2),
        new OA\Property(property: 'current_s', type: 'number', format: 'float', nullable: true, example: 415.1),
        new OA\Property(property: 'current_t', type: 'number', format: 'float', nullable: true, example: 408.9),
        new OA\Property(property: 'power_factor', type: 'number', format: 'float', nullable: true,
            description: 'Antara -1 dan 1.', example: 0.95),
        new OA\Property(property: 'frequency', type: 'number', format: 'float', nullable: true, example: 50),
    ],
)]
/*
 * Properti ditulis lengkap (tidak memakai allOf/$ref ke ReadingItem) supaya
 * Swagger UI bisa menyusun contoh JSON yang langsung bisa dijalankan lewat
 * "Try it out". Bentuk allOf membuat UI gagal membangun contoh, sehingga
 * kolom body tampil kosong dan meter_id tidak terlihat sama sekali.
 */
#[OA\Schema(
    schema: 'SingleReadingRequest',
    title: 'Kiriman tunggal',
    description: 'Bentuk paling umum: satu pembacaan per permintaan, dikirim tiap interval.',
    required: ['meter_id', 'read_at', 'stand_lwbp', 'stand_wbp'],
    properties: [
        new OA\Property(property: 'meter_id', type: 'integer',
            description: 'ID meter — kolom pertama pada halaman Power Meter Device, atau dari GET /api/v1/meters.',
            example: 1),
        new OA\Property(property: 'read_at', type: 'string', format: 'date-time',
            description: 'Waktu pembacaan di sisi meter. Dua format diterima: ISO 8601 dengan offset '
                .'(2026-08-13T10:35:00+07:00) atau "YYYY-MM-DD HH:MM:SS" (2026-08-13 10:35:00). '
                .'Tanpa offset, waktu dibaca sebagai WIB (Asia/Jakarta).',
            example: '2026-08-13T10:35:00+07:00'),
        new OA\Property(property: 'stand_lwbp', type: 'number', format: 'float',
            description: 'Stand kumulatif register LWBP (kWh).', example: 1270280.5),
        new OA\Property(property: 'stand_wbp', type: 'number', format: 'float',
            description: 'Stand kumulatif register WBP (kWh).', example: 414260.2),
        new OA\Property(property: 'active_power_kw', type: 'number', format: 'float', nullable: true, example: 412.6),
        new OA\Property(property: 'voltage_r', type: 'number', format: 'float', nullable: true, example: 380.1),
        new OA\Property(property: 'voltage_s', type: 'number', format: 'float', nullable: true, example: 379.8),
        new OA\Property(property: 'voltage_t', type: 'number', format: 'float', nullable: true, example: 380.4),
        new OA\Property(property: 'current_r', type: 'number', format: 'float', nullable: true, example: 410.2),
        new OA\Property(property: 'current_s', type: 'number', format: 'float', nullable: true, example: 415.1),
        new OA\Property(property: 'current_t', type: 'number', format: 'float', nullable: true, example: 408.9),
        new OA\Property(property: 'power_factor', type: 'number', format: 'float', nullable: true, example: 0.95),
        new OA\Property(property: 'frequency', type: 'number', format: 'float', nullable: true, example: 50),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'BatchReadingRequest',
    title: 'Kiriman batch',
    description: 'Dipakai saat gateway mengirim buffer yang tertahan selama offline. Maksimal 1000 baris, seluruhnya milik satu meter.',
    required: ['meter_id', 'readings'],
    properties: [
        new OA\Property(property: 'meter_id', type: 'integer',
            description: 'ID meter — berlaku untuk seluruh baris pada readings.', example: 1),
        new OA\Property(property: 'readings', type: 'array', maxItems: 1000,
            items: new OA\Items(ref: '#/components/schemas/ReadingItem')),
    ],
    type: 'object',
)]

#[OA\Schema(
    schema: 'DeviceStatusRequest',
    title: 'Kondisi perangkat',
    description: 'Informasi perangkat dan kondisi kelistrikan terakhir. Menimpa, tidak dicatat sebagai riwayat.',
    required: ['meter_id'],
    properties: [
        new OA\Property(property: 'meter_id', type: 'integer',
            description: 'ID meter dari halaman Power Meter Device.', example: 1),

        new OA\Property(property: 'signal_dbm', type: 'integer', nullable: true,
            description: 'Kekuatan sinyal WiFi dalam dBm. Selalu negatif; makin mendekati nol makin kuat.',
            minimum: -120, maximum: 0, example: -62),
        new OA\Property(property: 'ip_address', type: 'string', format: 'ipv4', nullable: true, example: '192.168.1.50'),
        new OA\Property(property: 'mac_address', type: 'string', nullable: true,
            description: 'Format dipisah titik dua atau tanda hubung.', example: 'A4:CF:12:9B:7E:01'),
        new OA\Property(property: 'firmware_version', type: 'string', nullable: true, maxLength: 50, example: '2.4.1'),

        new OA\Property(property: 'read_at', type: 'string', format: 'date-time', nullable: true,
            description: 'Waktu menurut perangkat. Bila kosong, dipakai waktu server saat kiriman diterima. '
                .'Dua format diterima: ISO 8601 dengan offset (2026-08-13T10:35:00+07:00) atau '
                .'"YYYY-MM-DD HH:MM:SS" (2026-08-13 10:35:00) yang dibaca sebagai WIB (Asia/Jakarta).',
            example: '2026-08-13T10:35:00+07:00'),
        new OA\Property(property: 'stand_lwbp', type: 'number', format: 'float', nullable: true, example: 1270280.5),
        new OA\Property(property: 'stand_wbp', type: 'number', format: 'float', nullable: true, example: 414260.2),
        new OA\Property(property: 'active_power_kw', type: 'number', format: 'float', nullable: true, example: 412.6),
        new OA\Property(property: 'voltage_r', type: 'number', format: 'float', nullable: true, example: 380.1),
        new OA\Property(property: 'voltage_s', type: 'number', format: 'float', nullable: true,
            description: 'Diabaikan untuk meter 1 phase.', example: 379.8),
        new OA\Property(property: 'voltage_t', type: 'number', format: 'float', nullable: true,
            description: 'Diabaikan untuk meter 1 phase.', example: 380.4),
        new OA\Property(property: 'current_r', type: 'number', format: 'float', nullable: true, example: 410.2),
        new OA\Property(property: 'current_s', type: 'number', format: 'float', nullable: true, example: 415.1),
        new OA\Property(property: 'current_t', type: 'number', format: 'float', nullable: true, example: 408.9),
        new OA\Property(property: 'power_factor', type: 'number', format: 'float', nullable: true, example: 0.95),
        new OA\Property(property: 'frequency', type: 'number', format: 'float', nullable: true, example: 50),
    ],
    type: 'object',
)]

// ── Schema respons ───────────────────────────────────────────────────────

#[OA\Schema(
    schema: 'DeviceStatusResult',
    title: 'Hasil pembaruan kondisi',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Kondisi perangkat diperbarui.'),
        new OA\Property(property: 'meter_id', type: 'integer', example: 1),
        new OA\Property(property: 'meter_code', type: 'string', example: 'AW9L-IRC38'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ],
)]

#[OA\Schema(
    schema: 'IngestResult',
    title: 'Hasil penerimaan',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Pembacaan diterima.'),
        new OA\Property(property: 'meter_id', type: 'integer', example: 1),
        new OA\Property(property: 'meter_code', type: 'string', example: 'AW9L-IRC38'),
        new OA\Property(property: 'stored', type: 'integer',
            description: 'Jumlah baris yang benar-benar tersimpan.', example: 3),
        new OA\Property(property: 'duplicate', type: 'integer',
            description: 'Baris yang diabaikan karena timestamp-nya sudah tercatat.', example: 0),
        new OA\Property(property: 'latest_read_at', type: 'string', format: 'date-time', nullable: true),
    ],
)]
#[OA\Schema(
    schema: 'MeterSummary',
    title: 'Ringkasan meter',
    properties: [
        new OA\Property(property: 'meter_id', type: 'integer', example: 1),
        new OA\Property(property: 'code', type: 'string', example: 'AW9L-IRC38'),
        new OA\Property(property: 'name', type: 'string', example: 'Main Distribution Panel'),
        new OA\Property(property: 'location', type: 'string', nullable: true),
        new OA\Property(property: 'multiplier', type: 'number', format: 'float',
            description: 'Rasio CT yang diterapkan aplikasi ke stand yang dikirim.', example: 1),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'maintenance'], example: 'active'),
        new OA\Property(property: 'connection_status', type: 'string',
            enum: ['online', 'offline', 'maintenance'], example: 'online'),
        new OA\Property(property: 'last_seen_at', type: 'string', format: 'date-time', nullable: true),
    ],
)]
#[OA\Schema(
    schema: 'ErrorMessage',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'API token tidak valid. Kirim lewat header X-Api-Token.'),
    ],
)]
#[OA\Schema(
    schema: 'ValidationError',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Payload tidak valid.'),
        new OA\Property(property: 'errors', type: 'object', additionalProperties: new OA\AdditionalProperties(
            type: 'array', items: new OA\Items(type: 'string'),
        )),
    ],
)]
class OpenApiSpec
{
}
