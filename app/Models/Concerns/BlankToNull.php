<?php

namespace App\Models\Concerns;

/**
 * Mengubah string kosong menjadi null untuk kolom bertipe tanggal dan angka.
 *
 * Kolom opsional pada form diisi lewat <input type="date"> dan
 * <input type="number">. Ketika pengguna MENGOSONGKAN isinya, browser dan
 * Livewire mengirim string kosong — bukan null — dan aturan `nullable` pada
 * validasi meloloskannya apa adanya. String itu lalu sampai ke MySQL:
 *
 *   SQLSTATE[22007]: Incorrect date value: '' for column 'contract_start'
 *
 * Di server produksi APP_DEBUG mati, sehingga pengguna hanya melihat halaman
 * error tanpa petunjuk apa pun — padahal ia cuma mengosongkan satu kolom yang
 * memang boleh kosong.
 *
 * Diperbaiki di model, bukan di tiap halaman: sumber string kosong bukan cuma
 * satu form, dan halaman baru yang menambahkan kolom opsional tidak akan
 * memunculkan error apa pun saat ditulis — kesalahannya baru terlihat ketika
 * ada pengguna yang benar-benar mengosongkan kolomnya.
 *
 * Kolom teks sengaja tidak disentuh: string kosong pada kolom teks adalah
 * nilai yang sah, dan mengubahnya jadi null akan mengubah arti data yang
 * sudah ada.
 */
trait BlankToNull
{
    /** Tipe cast yang tidak bisa menerima string kosong di database. */
    private const CASTS_TOLAK_KOSONG = [
        'date', 'datetime', 'immutable_date', 'immutable_datetime', 'timestamp',
        'int', 'integer', 'float', 'double', 'real', 'decimal',
    ];

    public function setAttribute($key, $value)
    {
        if ($value === '' && $this->menolakStringKosong($key)) {
            $value = null;
        }

        return parent::setAttribute($key, $value);
    }

    private function menolakStringKosong(string $key): bool
    {
        $cast = $this->getCasts()[$key] ?? null;

        if ($cast === null) {
            return false;
        }

        // 'decimal:2' dan 'datetime:Y-m-d' membawa argumen setelah titik dua.
        $tipe = strtolower(explode(':', $cast, 2)[0]);

        return in_array($tipe, self::CASTS_TOLAK_KOSONG, true);
    }
}
