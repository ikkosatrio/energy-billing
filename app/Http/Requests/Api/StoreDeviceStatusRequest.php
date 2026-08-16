<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Kondisi terakhir perangkat. Isinya sama seperti payload pembacaan, ditambah
 * informasi perangkat — bedanya kiriman ini TIDAK dicatat sebagai riwayat,
 * melainkan menimpa satu baris kondisi milik meter tersebut.
 */
class StoreDeviceStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi ditangani middleware AuthenticateGateway.
        return true;
    }

    public function rules(): array
    {
        return [
            'meter_id' => ['required', 'integer', 'exists:power_meters,id'],

            // --- Informasi perangkat ---
            // dBm selalu bernilai negatif; makin mendekati nol makin kuat.
            'signal_dbm' => ['nullable', 'integer', 'between:-120,0'],
            'ip_address' => ['nullable', 'ip'],
            'mac_address' => ['nullable', 'string', 'regex:/^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/'],
            'firmware_version' => ['nullable', 'string', 'max:50'],

            // --- Kondisi kelistrikan ---
            'read_at' => ['nullable', 'date'],
            'stand_lwbp' => ['nullable', 'numeric', 'min:0'],
            'stand_wbp' => ['nullable', 'numeric', 'min:0'],
            'active_power_kw' => ['nullable', 'numeric'],
            'voltage_r' => ['nullable', 'numeric'],
            'voltage_s' => ['nullable', 'numeric'],
            'voltage_t' => ['nullable', 'numeric'],
            'current_r' => ['nullable', 'numeric'],
            'current_s' => ['nullable', 'numeric'],
            'current_t' => ['nullable', 'numeric'],
            'power_factor' => ['nullable', 'numeric', 'between:-1,1'],
            'frequency' => ['nullable', 'numeric'],
        ];
    }

    public function messages(): array
    {
        return [
            'meter_id.required' => 'meter_id wajib diisi — lihat ID meter di halaman Power Meter Device.',
            'meter_id.exists' => 'meter_id tidak ditemukan.',
            'mac_address.regex' => 'Format MAC address tidak dikenal, contoh: A4:CF:12:9B:7E:01.',
            'signal_dbm.between' => 'Kekuatan sinyal dalam dBm, bernilai antara -120 sampai 0.',
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
