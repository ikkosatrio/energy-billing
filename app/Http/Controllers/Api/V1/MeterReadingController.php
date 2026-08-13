<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMeterReadingRequest;
use App\Models\PowerMeter;
use App\Services\Monitoring\ReadingIngestService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Meter Readings',
    description: 'Pengiriman pembacaan power meter dari gateway IoT ke aplikasi.',
)]
class MeterReadingController extends Controller
{
    public function __construct(private readonly ReadingIngestService $ingest)
    {
    }

    #[OA\Post(
        path: '/api/v1/readings',
        operationId: 'storeMeterReadings',
        summary: 'Kirim pembacaan power meter',
        description: <<<'TXT'
        Menerima satu pembacaan atau sekumpulan pembacaan untuk **satu meter**.

        `stand_lwbp` dan `stand_wbp` adalah **angka kumulatif meter**, bukan
        pemakaian per interval. Tagihan dihitung dari selisih stand awal dan
        akhir periode, sehingga data yang bolong di tengah periode tidak
        merusak perhitungan.

        Pembacaan dengan `read_at` yang sudah tercatat akan **diabaikan**, bukan
        ditimpa — aman bila gateway mengirim ulang buffer setelah jaringan pulih.

        Nilai stand dikalikan `multiplier` (rasio CT) milik meter sebelum
        disimpan, jadi kirim angka apa adanya dari perangkat.
        TXT,
        security: [['ApiToken' => []]],
        tags: ['Meter Readings'],
    )]
    /*
     * `examples` di sini yang membuat "Try it out" terisi JSON siap jalan.
     * Swagger UI menampilkannya sebagai dropdown pilihan contoh; tanpa ini
     * kolom body tampil kosong karena skema oneOf tidak bisa disimpulkan
     * menjadi satu contoh oleh UI.
     */
    #[OA\RequestBody(
        required: true,
        description: 'meter_id dikirim di dalam body JSON, bukan di URL. Pilih contoh "Kiriman tunggal" atau "Kiriman batch" pada dropdown di bawah.',
        content: new OA\JsonContent(
            oneOf: [
                new OA\Schema(ref: '#/components/schemas/SingleReadingRequest'),
                new OA\Schema(ref: '#/components/schemas/BatchReadingRequest'),
            ],
            examples: [
                'tunggal' => new OA\Examples(
                    example: 'tunggal',
                    summary: 'Kiriman tunggal — dipakai tiap interval',
                    value: [
                        'meter_id' => 1,
                        'read_at' => '2026-08-13T10:35:00+07:00',
                        'stand_lwbp' => 1270280.5,
                        'stand_wbp' => 414260.2,
                        'active_power_kw' => 412.6,
                        'voltage_r' => 380.1,
                        'voltage_s' => 379.8,
                        'voltage_t' => 380.4,
                        'current_r' => 410.2,
                        'current_s' => 415.1,
                        'current_t' => 408.9,
                        'power_factor' => 0.95,
                        'frequency' => 50,
                    ],
                ),
                'minimal' => new OA\Examples(
                    example: 'minimal',
                    summary: 'Kiriman minimal — hanya field wajib',
                    value: [
                        'meter_id' => 1,
                        'read_at' => '2026-08-13T10:35:00+07:00',
                        'stand_lwbp' => 1270280.5,
                        'stand_wbp' => 414260.2,
                    ],
                ),
                'batch' => new OA\Examples(
                    example: 'batch',
                    summary: 'Kiriman batch — buffer setelah gateway offline',
                    value: [
                        'meter_id' => 1,
                        'readings' => [
                            ['read_at' => '2026-08-13T10:33:00+07:00', 'stand_lwbp' => 1270260.1, 'stand_wbp' => 414258.0, 'active_power_kw' => 408.2],
                            ['read_at' => '2026-08-13T10:34:00+07:00', 'stand_lwbp' => 1270270.3, 'stand_wbp' => 414259.1, 'active_power_kw' => 410.9],
                            ['read_at' => '2026-08-13T10:35:00+07:00', 'stand_lwbp' => 1270280.5, 'stand_wbp' => 414260.2, 'active_power_kw' => 412.6],
                        ],
                    ],
                ),
            ],
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'Pembacaan diterima.',
        content: new OA\JsonContent(ref: '#/components/schemas/IngestResult'),
    )]
    #[OA\Response(response: 401, description: 'API token tidak valid.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage'))]
    #[OA\Response(response: 403, description: 'Meter berstatus nonaktif.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage'))]
    #[OA\Response(response: 422, description: 'Payload tidak valid.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function store(StoreMeterReadingRequest $request): JsonResponse
    {
        $meter = PowerMeter::findOrFail($request->validated('meter_id'));

        // Meter nonaktif berarti sudah dicabut dari layanan; datanya tidak
        // boleh masuk lagi agar tidak ikut terhitung ke tagihan.
        if ($meter->status === 'inactive') {
            return response()->json(['message' => 'Meter berstatus nonaktif.'], 403);
        }

        $result = $this->ingest->store($meter, $request->validated('readings'));

        return response()->json([
            'message' => 'Pembacaan diterima.',
            'meter_id' => $meter->id,
            'meter_code' => $meter->code,
            'stored' => $result['stored'],
            'duplicate' => $result['duplicate'],
            'latest_read_at' => $result['latest_read_at'],
        ], 201);
    }

    #[OA\Get(
        path: '/api/v1/meters',
        operationId: 'listMeters',
        summary: 'Daftar ID meter',
        description: 'Dipakai gateway untuk memetakan perangkat di lapangan ke `meter_id` yang benar. Daftar yang sama juga terlihat di halaman Power Meter Device.',
        security: [['ApiToken' => []]],
        tags: ['Meter Readings'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Daftar meter yang aktif menerima data.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/MeterSummary'),
                ),
            ],
        ),
    )]
    #[OA\Response(response: 401, description: 'API token tidak valid.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage'))]
    public function meters(): JsonResponse
    {
        $meters = PowerMeter::where('status', '!=', 'inactive')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'location', 'multiplier', 'status', 'last_seen_at']);

        return response()->json([
            'data' => $meters->map(fn (PowerMeter $meter) => [
                'meter_id' => $meter->id,
                'code' => $meter->code,
                'name' => $meter->name,
                'location' => $meter->location,
                'multiplier' => (float) $meter->multiplier,
                'status' => $meter->status,
                'connection_status' => $meter->connection_status,
                'last_seen_at' => $meter->last_seen_at?->toIso8601String(),
            ]),
        ]);
    }

    #[OA\Get(
        path: '/api/v1/ping',
        operationId: 'ping',
        summary: 'Cek koneksi & konfigurasi',
        description: 'Dipakai gateway saat start-up untuk memastikan token masih berlaku dan mengambil interval push yang dikonfigurasi di aplikasi.',
        security: [['ApiToken' => []]],
        tags: ['Meter Readings'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Server siap menerima data.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'OK'),
                new OA\Property(property: 'server_time', type: 'string', format: 'date-time'),
                new OA\Property(property: 'push_interval_seconds', type: 'integer', example: 60),
            ],
        ),
    )]
    #[OA\Response(response: 401, description: 'API token tidak valid.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage'))]
    public function ping(): JsonResponse
    {
        return response()->json([
            'message' => 'OK',
            'server_time' => now()->toIso8601String(),
            'push_interval_seconds' => (int) setting('iot_push_interval_seconds', 60),
        ]);
    }
}
