# Energy Billing

Aplikasi tagihan pemakaian listrik gudang berbasis IoT power meter.

Pemilik gudang menyewakan unit ke pelanggan. Tiap pelanggan punya satu power
meter yang mengirim pembacaan stand kWh (LWBP & WBP terpisah) ke aplikasi
setiap menit. Pada tanggal cut-off, aplikasi menghitung selisih stand awal dan
akhir periode lalu menerbitkan invoice otomatis.

## Stack

- Laravel 10 (PHP 8.2) + Livewire 3
- Laravel Octane + FrankenPHP
- MySQL — **satu database** (`main`)
- Tailwind CSS (build manual) + Blade, ikon Lucide

## Modul

| Modul               | Isi                                                                |
| ------------------- | ------------------------------------------------------------------ |
| Dashboard           | Ringkasan pemakaian, tagihan berjalan, status meter, invoice terbaru |
| Monitoring          | Real-time meter, energy history (jam/hari/bulan), status perangkat  |
| Billing & Invoice   | Periode billing, generate invoice, pembayaran + bukti transfer      |
| Master Data         | Data pelanggan (= gudang), power meter device                       |
| Tarif & Konfigurasi | Golongan tarif berperiode, jadwal WBP/LWBP                          |
| Report              | Rekap pemakaian kWh, rekap tagihan & penerimaan (Excel/PDF)         |
| Sistem              | Setting aplikasi, user, role & hak akses, log aktivitas             |

## Aturan bisnis utama

- **1 pelanggan = 1 gudang = 1 power meter.**
- Pemakaian dihitung dari **selisih stand meter** awal & akhir periode, bukan
  akumulasi delta interval — tahan terhadap data gateway yang bolong.
- Meter mengirim register **LWBP dan WBP terpisah**; aplikasi menyimpan apa
  adanya. Jadwal WBP/LWBP hanya referensi konfigurasi, tidak dipakai membagi kWh.
- Tarif disimpan per **golongan + masa berlaku**. Mengubah tarif = menambah
  baris baru, tarif lama tetap utuh.
- Seluruh angka, tarif, dan identitas pelanggan **di-snapshot ke invoice** saat
  generate, sehingga invoice lama tidak ikut berubah ketika data induk diperbarui.
- Tanggal generate invoice memakai **default global** dari setting sistem, dan
  boleh di-override per pelanggan (`customers.billing_day`).
- Stand meter yang mundur (reset/rollover) **dinolkan dan ditandai** di catatan
  invoice, tidak pernah menghasilkan pemakaian negatif.

## Setup lokal

```bash
composer install
```

```bash
cp .env.example .env && php artisan key:generate
```

Buat database sesuai `DB_DATABASE_MAIN`, lalu:

```bash
php artisan migrate --seed
```

Build CSS dan jalankan:

```bash
npm install && npm run tw:build && php artisan serve
```

Login awal: `admin` / `password` — **segera ganti setelah login pertama.**
Bisa diubah lewat `SEED_ADMIN_USERNAME`, `SEED_ADMIN_EMAIL`, dan
`SEED_ADMIN_PASSWORD` di `.env` sebelum menjalankan seeder.

### Data contoh

```bash
php artisan db:seed --class=DemoDataSeeder
```

Mengisi 6 pelanggan, 6 meter, pembacaan dua bulan terakhir, agregat harian,
dan invoice bulan lalu. Hanya berjalan bila tabel pelanggan masih kosong.

### Selama menggarap tampilan

```bash
npm run tw:watch
```

## Endpoint gateway IoT

**Dokumentasi interaktif (Swagger UI): `/api/documentation`** — bisa dibuka dari
menu Sistem → Dokumentasi API. Halaman ini berada di balik login aplikasi.

```
POST /api/v1/readings   Kirim pembacaan
GET  /api/v1/meters     Daftar meter beserta ID-nya
GET  /api/v1/ping       Cek token & interval push
```

### Identifikasi meter

Meter disebut lewat **`meter_id`** — ID numerik yang tampil di kolom pertama
halaman **Power Meter Device** dan bisa dilihat kapan saja. Tidak ada rahasia
per perangkat.

`meter_id` dikirim **di dalam body JSON**, bukan sebagai parameter URL. Di
Swagger UI: buka `POST /api/v1/readings` → **Try it out** → pilih contoh pada
dropdown (*Kiriman tunggal* / *minimal* / *batch*), body langsung terisi dan
tinggal dijalankan.

### Autentikasi

Seluruh gateway memakai satu API token global:

```
X-Api-Token: <token>
```

Token diatur di **Setting Aplikasi → Integrasi IoT**, terlihat permanen, bisa
disalin dan digenerate ulang kapan saja. `Authorization: Bearer <token>` juga
diterima.

Mengosongkan token akan **mematikan autentikasi** — endpoint jadi terbuka.
Karena data yang masuk situ langsung menentukan nominal tagihan, hanya lakukan
itu bila server benar-benar tertutup di jaringan internal.

### Payload

Tunggal:

```json
{
  "meter_id": 1,
  "read_at": "2026-08-13T10:35:00+07:00",
  "stand_lwbp": 1270280.5,
  "stand_wbp": 414260.2,
  "active_power_kw": 412.6,
  "voltage_r": 380.1,
  "current_r": 410.2,
  "power_factor": 0.95,
  "frequency": 50
}
```

Batch — dipakai saat gateway mengirim buffer setelah offline, maksimal 1000
baris dan seluruhnya milik satu meter:

```json
{
  "meter_id": 1,
  "readings": [
    { "read_at": "2026-08-13T10:35:00+07:00", "stand_lwbp": 1270280.5, "stand_wbp": 414260.2 }
  ]
}
```

`stand_lwbp` dan `stand_wbp` adalah **angka kumulatif meter**, bukan pemakaian.
Pengiriman ulang untuk timestamp yang sama diabaikan, tidak menimpa dan tidak
menggandakan.

### Pilihan server di Swagger

Dropdown **Servers** menentukan host yang ditembak tombol *Try it out*:

| Entri | Asal |
| ----- | ---- |
| `/` — Server saat ini | Relatif; selalu mengikuti host yang membuka halaman docs, jadi benar di local, staging, maupun produksi tanpa konfigurasi |
| Staging | `SWAGGER_SERVER_STAGING` di `.env` |
| Produksi | `SWAGGER_SERVER_PRODUCTION` di `.env` |

```dotenv
SWAGGER_SERVER_STAGING=https://staging.perusahaan.co.id
SWAGGER_SERVER_PRODUCTION=https://billing.perusahaan.co.id
```

Argumen atribut PHP harus berupa constant expression, jadi `env()` tidak bisa
dipanggil langsung di `#[OA\Server]`. Nilainya dijembatani lewat kunci
`constants` di [config/l5-swagger.php](config/l5-swagger.php) — tambah entri di
sana bila perlu lingkungan lain (mis. DR atau demo).

### Tampilan halaman dokumentasi

Halaman Swagger memakai identitas aplikasi, bukan tampilan bawaan Swagger:
header navy dengan logo dan nama dari **Setting Aplikasi**, palet dan font yang
sama dengan aplikasi, serta tombol kembali ke dashboard.

| Berkas | Peran |
| ------ | ----- |
| [resources/views/vendor/l5-swagger/index.blade.php](resources/views/vendor/l5-swagger/index.blade.php) | Header aplikasi, memuat font & stylesheet, `layout: BaseLayout` agar topbar bawaan tidak dirender |
| [public/assets/css/swagger-theme.css](public/assets/css/swagger-theme.css) | Menerapkan token dari `app.css` ke selector Swagger UI |

Logo dan nama ikut berubah otomatis saat diganti di menu Setting — tidak ada
yang perlu di-hardcode.

> Bila paket l5-swagger di-update dan view-nya di-publish ulang, penyesuaian di
> `index.blade.php` akan tertimpa dan perlu diterapkan kembali.

### Regenerasi dokumentasi

```bash
php artisan l5-swagger:generate
```

Wajib dijalankan ulang setiap anotasi atau URL server berubah. Saat development,
set `L5_SWAGGER_GENERATE_ALWAYS=true` di `.env` agar menyesuaikan sendiri tiap
request.

## Perintah terjadwal

Scheduler harus berjalan agar invoice dan agregat terbentuk otomatis:

```bash
php artisan schedule:work
```

| Perintah                  | Jadwal            | Fungsi                                         |
| ------------------------- | ----------------- | ---------------------------------------------- |
| `readings:aggregate`      | tiap jam          | Meringkas pembacaan menjadi agregat harian     |
| `invoices:generate`       | harian, jam setting | Menerbitkan invoice pelanggan yang jatuh tempo |
| `invoices:mark-overdue`   | harian 01:00      | Menandai invoice lewat jatuh tempo             |
| `readings:prune`          | mingguan          | Menghapus pembacaan mentah di luar masa retensi |

## Testing

```bash
./vendor/bin/phpunit
```

Test memakai database terpisah `energy_billing_test` (lihat `phpunit.xml`) dan
memigrasi ulang dari nol, sehingga tidak menyentuh data development.

## Docker

```bash
docker compose up -d --build
```

Aplikasi tersedia di `http://localhost:8000` (ubah lewat `APP_PORT`).
Health check container: `GET /health`.

`config.platform.php` di `composer.json` dikunci ke **8.2** menyamai image
Docker. Tanpa itu, `composer install` di mesin dev ber-PHP lebih baru akan
mengunci paket yang tidak bisa dipasang di dalam container.

## Catatan keamanan

- **Laravel 10 sudah lewat masa dukungan keamanan.** `composer audit` melaporkan
  3 advisory yang perbaikannya hanya ada di Laravel 12, termasuk *CRLF injection
  in default email rule*. Sebagai penambal sementara, seluruh field email
  memakai aturan `email:filter` (FILTER_VALIDATE_EMAIL) yang menolak CRLF.
  **Upgrade ke Laravel 12 disarankan** sebelum aplikasi dipakai di produksi.
- **API token gateway bersifat global dan disimpan sebagai teks biasa** di tabel
  `settings`, supaya bisa dilihat dan disalin kapan saja dari halaman Setting.
  Konsekuensinya: satu token bocor berarti seluruh gateway harus dikonfigurasi
  ulang, dan token tidak membatasi meter mana yang boleh dikirimi data — siapa
  pun yang memegangnya bisa mengirim untuk `meter_id` mana saja. Generate ulang
  token bila ada kecurigaan kebocoran.
- Endpoint ingest menentukan angka tagihan. Jalankan aplikasi di belakang HTTPS
  agar token tidak terbaca di jaringan.

## Referensi desain

`template-ref/` berisi mockup desain (HTML + screenshot) yang dipakai sebagai
acuan UI. Folder ini tidak ikut ter-deploy dan sudah masuk `.gitignore`.
