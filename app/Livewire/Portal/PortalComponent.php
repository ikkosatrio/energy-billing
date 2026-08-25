<?php

namespace App\Livewire\Portal;

use App\Models\CustomerUser;
use Livewire\Component;

/**
 * Basis seluruh halaman Livewire portal.
 *
 * Ada supaya batas data hanya ditulis di SATU tempat. Aksi Livewire dipanggil
 * lewat endpoint-nya sendiri, bukan lewat route halaman — jadi middleware
 * route tidak ikut melindunginya, dan setiap komponen wajib memeriksa ulang.
 * Menyerahkan itu ke ingatan penulis komponen berarti cepat atau lambat ada
 * satu halaman yang lupa, dan halaman itulah yang membocorkan data pelanggan
 * lain.
 *
 * Turunannya WAJIB mengisi $permission, lalu memakai meterIds()/customerIds()
 * untuk setiap query — bukan mengambil id dari properti publik komponen, yang
 * bisa diubah dari sisi browser.
 */
abstract class PortalComponent extends Component
{
    /** Slug permission portal yang wajib dipunyai untuk membuka halaman ini. */
    protected string $permission = '';

    public function boot(): void
    {
        // Dijalankan pada setiap request komponen, termasuk saat aksi
        // dipanggil — bukan hanya saat halaman pertama dimuat.
        abort_unless(auth(CustomerUser::GUARD)->check(), 403);

        if ($this->permission !== '') {
            $this->authorize($this->permission);
        }
    }

    protected function portalUser(): CustomerUser
    {
        return auth(CustomerUser::GUARD)->user();
    }

    /**
     * Pelanggan yang boleh dilihat. Selalu dipakai sebagai batas query,
     * bukan sebagai pilihan awal yang bisa diganti dari browser.
     *
     * @return array<int, int>
     */
    protected function customerIds(): array
    {
        return $this->portalUser()->accessibleCustomerIds();
    }

    /** @return array<int, int> */
    protected function meterIds(): array
    {
        return $this->portalUser()->accessibleMeterIds();
    }

    /**
     * Memvalidasi id pelanggan yang datang dari filter di layar.
     *
     * Null berarti "semua yang boleh diakses". Id di luar daftar akses
     * ditolak dengan 403, bukan diam-diam diganti jadi null — permintaan
     * seperti itu tidak mungkin berasal dari tampilan yang sah.
     *
     * @return array<int, int> daftar id yang aman dipakai pada whereIn
     */
    protected function scopedCustomerIds(?int $selected): array
    {
        if ($selected === null) {
            return $this->customerIds();
        }

        abort_unless($this->portalUser()->canAccessCustomer($selected), 403);

        return [$selected];
    }

    /**
     * Padanan scopedCustomerIds() untuk meter.
     *
     * @return array<int, int>
     */
    protected function scopedMeterIds(?int $selected): array
    {
        if ($selected === null) {
            return $this->meterIds();
        }

        abort_unless($this->portalUser()->canAccessMeter($selected), 403);

        return [$selected];
    }
}
