<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMeterReadingRequest;
use App\Models\PowerMeter;
use App\Services\Monitoring\ReadingIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeterReadingController extends Controller
{
    public function __construct(private readonly ReadingIngestService $ingest)
    {
    }

    /**
     * POST /api/v1/readings
     *
     * Menerima pembacaan power meter dari gateway IoT. Meter diidentifikasi
     * lewat header X-Device-Key (lihat middleware AuthenticateDevice).
     */
    public function store(StoreMeterReadingRequest $request): JsonResponse
    {
        /** @var PowerMeter $meter */
        $meter = $request->attributes->get('power_meter');

        $result = $this->ingest->store($meter, $request->validated('readings'));

        return response()->json([
            'message' => 'Pembacaan diterima.',
            'meter' => $meter->code,
            'stored' => $result['stored'],
            'duplicate' => $result['duplicate'],
            'latest_read_at' => $result['latest_read_at'],
        ], 201);
    }

    /**
     * GET /api/v1/ping — dipakai gateway untuk memeriksa apakah device key
     * masih berlaku sebelum mulai mengirim data.
     */
    public function ping(Request $request): JsonResponse
    {
        /** @var PowerMeter $meter */
        $meter = $request->attributes->get('power_meter');

        return response()->json([
            'message' => 'OK',
            'meter' => $meter->code,
            'server_time' => now()->toIso8601String(),
            'push_interval_seconds' => (int) setting('iot_push_interval_seconds', 60),
        ]);
    }
}
