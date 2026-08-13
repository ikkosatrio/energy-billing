<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Menerima satu pembacaan maupun kiriman batch untuk SATU meter.
 *
 * Meter ditentukan oleh `meter_id` di tingkat atas payload. Nilainya adalah ID
 * yang tampil di halaman Power Meter dan bisa dilihat kapan saja.
 *
 * Batch penting karena gateway menyimpan data saat jaringan putus lalu
 * mengirimnya sekaligus ketika kembali online.
 *
 *   { "meter_id": 1, "read_at": "...", "stand_lwbp": 1, "stand_wbp": 2 }
 *   { "meter_id": 1, "readings": [ {...}, {...} ] }
 */
class StoreMeterReadingRequest extends FormRequest
{
    /** Batas jumlah baris per kiriman batch. */
    public const MAX_BATCH = 1000;

    public function authorize(): bool
    {
        // Autentikasi ditangani middleware AuthenticateGateway.
        return true;
    }

    /**
     * Membungkus payload tunggal menjadi bentuk batch agar aturan validasi
     * dan controller hanya perlu menangani satu bentuk data.
     */
    protected function prepareForValidation(): void
    {
        if (!$this->has('readings')) {
            $this->merge([
                'readings' => [$this->except('meter_id')],
            ]);
        }
    }

    public function rules(): array
    {
        return [
            // Meter nonaktif tidak boleh lagi menerima data; ini divalidasi di
            // sini agar gateway mendapat 422 dengan pesan yang jelas.
            'meter_id' => ['required', 'integer', 'exists:power_meters,id'],
            'readings' => ['required', 'array', 'min:1', 'max:'.self::MAX_BATCH],
            'readings.*.read_at' => ['required', 'date'],
            'readings.*.stand_lwbp' => ['required', 'numeric', 'min:0'],
            'readings.*.stand_wbp' => ['required', 'numeric', 'min:0'],
            'readings.*.active_power_kw' => ['nullable', 'numeric'],
            'readings.*.voltage_r' => ['nullable', 'numeric'],
            'readings.*.voltage_s' => ['nullable', 'numeric'],
            'readings.*.voltage_t' => ['nullable', 'numeric'],
            'readings.*.current_r' => ['nullable', 'numeric'],
            'readings.*.current_s' => ['nullable', 'numeric'],
            'readings.*.current_t' => ['nullable', 'numeric'],
            'readings.*.power_factor' => ['nullable', 'numeric', 'between:-1,1'],
            'readings.*.frequency' => ['nullable', 'numeric'],
        ];
    }

    public function messages(): array
    {
        return [
            'meter_id.required' => 'meter_id wajib diisi — lihat ID meter di halaman Power Meter Device.',
            'meter_id.exists' => 'meter_id tidak ditemukan.',
            'readings.max' => 'Maksimal '.self::MAX_BATCH.' pembacaan per kiriman.',
            'readings.*.stand_lwbp.required' => 'stand_lwbp wajib diisi (angka kumulatif meter).',
            'readings.*.stand_wbp.required' => 'stand_wbp wajib diisi (angka kumulatif meter).',
        ];
    }

    /**
     * Gateway bukan browser: balas JSON, bukan redirect.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Payload tidak valid.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
