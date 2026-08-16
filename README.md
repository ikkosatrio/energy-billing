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
| Report              | Rekap pemakaian kWh, rekap tagihan & penerimaan, data meter mentah  |
| Sistem              | Setting aplikasi, user, role & hak akses, log aktivitas             |

## Kartu Real-time Monitoring

Tiap power meter tampil sebagai satu kartu yang sengaja dibagi dua bagian,
karena isinya bergerak dengan kecepatan yang sangat berbeda:

| Bagian | Isi | Berubah |
| ------ | --- | ------- |
| **Sekarang** | Stand register LWBP & WBP (kWh), tegangan & arus tiap jalur | tiap gateway mengirim |
| **Pemakaian** | kWh + rupiah hari ini, minggu berjalan, bulan berjalan | sekali sehari |
| **Pemakaian harian** | Satu batang per tanggal sejak awal bulan, hari terboros disorot | sekali sehari |

Definisi rentangnya:

- **Hari ini** — sejak tengah malam, dihitung langsung dari `meter_readings`.
  Agregat harian baru terisi setelah job agregasi berjalan, jadi memakainya
  akan membuat angka hari ini tertinggal.
- **Minggu berjalan** — Senin sampai hari ini. Bila minggunya dimulai di bulan
  sebelumnya, sisa hari di bulan lalu tetap ikut dihitung.
- **Bulan berjalan** — tanggal 1 sampai hari ini.
- **Tertinggi** — hari dengan pemakaian kWh terbesar bulan ini, beserta
  tanggalnya. Batangnya disorot di grafik dengan warna yang sama dengan titik
  pada keterangan di bawahnya. Batang hari ini diarsir karena angkanya masih
  akan bertambah sampai tengah malam.

> **Rupiah di kartu ini adalah estimasi biaya energi saja** — `kWh LWBP × tarif
> + kWh WBP × tarif`, memakai tarif yang berlaku hari ini. Biaya beban, biaya
> admin, PPJ, dan PPN tidak ikut karena ketiganya berlaku per bulan; membaginya
> ke angka "hari ini" hanya menghasilkan angka yang kelihatan pasti padahal
> mengada-ada. **Nominal yang sah tetap datang dari invoice.**
>
> Golongan yang belum punya tarif berlaku menampilkan "tarif belum diatur",
> bukan Rp 0 — kWh-nya tetap benar, hanya rupiahnya yang belum bisa dihitung.

Hari-hari sebelum hari ini dibaca dari `meter_readings_daily`, bukan dari
pembacaan mentah: memuat sebulan penuh data mentah untuk seluruh meter tiap
kali polling akan menghabiskan memori. Angka hari ini **menggantikan** baris
agregat hari ini, tidak ditambahkan, supaya tidak terhitung dua kali.

Perhitungannya ada di
[UsageSummaryService](app/Services/Monitoring/UsageSummaryService.php) dan
memakai [ConsumptionCalculator](app/Services/Monitoring/ConsumptionCalculator.php)
yang sama dengan tagihan, sehingga meter yang di-reset tidak menghasilkan angka
raksasa di kartu.

### Jeda penyegaran

Jedanya dipilih sendiri lewat deret tombol di kanan atas: **5s · 10s · 30s ·
1m · 5m · 10m · Manual**. Default 30 detik, dan pilihannya disimpan di sesi
sehingga bertahan saat pindah halaman lalu kembali. **Manual** mematikan
polling sepenuhnya; tombol segarkan di sebelahnya tetap tersedia pada jeda apa
pun.

Setiap penyegaran memuat ulang pembacaan mentah hari ini untuk seluruh meter
aktif. Pada jeda 5 detik itu berarti 12× lebih banyak query per menit
dibanding 1 menit — aman untuk puluhan meter, tapi bila jumlah meternya sudah
ratusan, pilih jeda yang lebih panjang atau pindahkan sumber angka hari ini ke
agregat harian.

### Filter jenis sambungan

**Real-time Monitoring** dan **Master Data → Power Meter Device** punya filter
1 phase / 3 phase. Di halaman monitoring, tiap pilihan menampilkan jumlah
perangkatnya sehingga terlihat ada berapa sebelum filternya dipilih.

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
- Stand meter yang mundur (reset/rollover) tetap **dihitung sejak titik reset**
  dan ditandai — lihat bagian di bawah.

## Penanganan meter yang di-reset

Seluruh perhitungan kWh memakai **penjumlahan selisih antar pembacaan
berurutan**, bukan sekadar stand akhir dikurangi stand awal. Bedanya baru
terasa ketika meter di-reset di tengah periode.

Contoh: meter dipakai 300 kWh, di-reset ke 0, lalu dipakai 80 kWh lagi —
pemakaian sebenarnya 380 kWh.

| Cara hitung | Hasil |
| ----------- | ----- |
| `max(0, akhir − awal)` | 0 — seluruh pemakaian hilang, pelanggan tidak ditagih |
| `MAX(stand) − MIN(stand)` | 9.300 — membengkak 24×, tanpa tanda apa pun |
| **Jumlah selisih positif** | **380** ✓ |

Diterapkan di enam tempat: invoice, agregat harian, chart per jam, kWh hari ini
di monitoring, ringkasan data mentah, dan seluruh rekap yang membaca agregat
harian. Dikunci oleh `tests/Feature/MeterResetTest.php`.

### Titik awal periode

Stand awal diambil dari pembacaan **terakhir sebelum periode**, yaitu stand
akhir periode sebelumnya — sama seperti tagihan listrik pada umumnya. Agregat
harian juga memakai pembacaan terakhir sebelum hari itu sebagai titik awal.

Tanpa ini, pemakaian di setiap pergantian hari tidak masuk ke hari mana pun:
hilang satu interval per hari, dan Rekap Pemakaian membaca lebih rendah
daripada yang ditagihkan invoice (pada interval 30 menit selisihnya ~2% sebulan).

Dengan aturan yang sama di kedua tempat, **jumlah agregat harian sepanjang
periode sama persis dengan angka pada invoice** — diuji pada interval 1, 5, dan
30 menit.

Pelanggan baru yang belum punya pembacaan sebelumnya memakai pembacaan pertama
di dalam periode sebagai titik awal.

### Cara reset dideteksi

Jalur normal tetap memakai selisih stand yang murah; penjumlahan selisih hanya
ditempuh ketika reset terdeteksi. Tiga pemicunya:

1. Stand terakhir lebih kecil dari stand pertama
2. `SUM(reset_count)` pada agregat harian periode itu lebih dari nol
3. Periode itu belum punya agregat harian sama sekali

Poin 2 yang menutup lubang paling berbahaya. Membandingkan stand pertama dengan
stand terakhir saja **tidak cukup**: meter yang di-reset berkali-kali bisa
berakhir di angka lebih tinggi daripada awalnya. Contoh nyata — 100 siklus
naik-lalu-reset yang berhenti di stand 20, dari stand awal 0. Pemeriksaan
akhir-vs-awal membacanya sebagai normal dan menagih **20 kWh dari 5.020 kWh**
yang sebenarnya terpakai, tanpa peringatan apa pun.

Agregat harian menutupnya karena `reset_count`-nya dihitung dengan menelusuri
seluruh pembacaan hari itu — reset di tengah periode tetap tertangkap. Query-nya
murah: satu baris per hari, bukan per pembacaan. Dikunci
`test_reset_berulang_yang_berakhir_di_stand_lebih_tinggi`.

Poin 3 memastikan periode tanpa agregat harian tidak pernah ditagih dari selisih
stand yang belum terverifikasi — **jadi scheduler `readings:aggregate` wajib
berjalan.**

### Reset vs rollover

Keduanya sama-sama terlihat sebagai stand yang mundur, tapi pemakaiannya
berbeda dan ditangani berbeda:

| Kejadian | Penanganan |
| -------- | ---------- |
| **Reset** — meter diganti/dinolkan teknisi | Pemakaian dihitung dari nol, sebesar angka barunya |
| **Rollover** — register penuh lalu berputar | Sisa pemakaian sampai titik putar ikut dihitung |

Rollover butuh **Angka Maksimum Register** pada data power meter (mis.
`999999.99` untuk register 6 digit, diisi apa adanya dari spesifikasi meter —
pengali CT diterapkan aplikasi).

Membedakannya memakai kedekatan ke batas: stand 999.980 yang jatuh ke 30 adalah
rollover, sedangkan 9.000 yang jatuh ke 80 pada meter berbatas 999.999 jelas
penggantian meter — dan tidak boleh ditambahi ~990.000 kWh yang tidak pernah
dipakai.

**Bila kolom itu dikosongkan**, rollover diperlakukan sebagai reset. Aman, tapi
sisa pemakaian sampai titik putar hilang — besarnya terbatas pada pemakaian
satu interval pembacaan (contoh nyata: 19,99 kWh dari 129,99 kWh).

Seluruh kejadian ditandai di catatan invoice, kolom `reset_count` pada agregat
harian, dan kolom Catatan pada laporan Data Meter Mentah. Invoice yang terkena
tetap berstatus draft walau penerbitan otomatis menyala.

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
POST /api/v1/readings   Kirim pembacaan (dicatat sebagai riwayat)
POST /api/v1/status     Kirim kondisi terakhir perangkat (ditimpa, tanpa riwayat)
GET  /api/v1/meters     Daftar meter beserta ID-nya
GET  /api/v1/ping       Cek token & interval push
```

Dua endpoint kirim itu berbeda tujuan dan **tidak saling menggantikan**:

| | `/readings` | `/status` |
|---|---|---|
| Disimpan sebagai | baris baru di `meter_readings` | satu baris per meter, ditimpa terus |
| Riwayat | ya — dasar seluruh tagihan | tidak ada |
| Dipakai untuk | invoice, laporan, grafik | melihat kondisi terkini perangkat |
| Frekuensi wajar | sesuai interval push | sesering apa pun |

Keduanya menyegarkan `last_seen_at`, jadi gateway yang hanya mengirim status
tetap terbaca *online* di halaman monitoring.

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

### Kondisi perangkat — `POST /api/v1/status`

Informasi yang hanya perlu diketahui keadaan terakhirnya: kekuatan sinyal WiFi,
alamat IP, MAC address, dan versi firmware. Tidak ada riwayat yang ditulis, jadi
gateway boleh mengirimnya sesering mungkin tanpa membebani tabel pembacaan.

```json
{
  "meter_id": 1,
  "signal_dbm": -62,
  "ip_address": "192.168.10.21",
  "mac_address": "A4:CF:12:9B:00:7E",
  "firmware_version": "1.4.2",
  "active_power_kw": 412.6,
  "voltage_r": 229.6,
  "current_r": 41.2,
  "power_factor": 0.95,
  "frequency": 50
}
```

Semua field kecuali `meter_id` bersifat opsional, dan **field yang tidak dikirim
dibiarkan apa adanya** — bukan dihapus. Gateway boleh mengirim status ringkas
(mis. hanya `signal_dbm`) tanpa menghilangkan IP dan firmware yang sudah
tercatat.

`signal_dbm` diterima pada rentang −120 sampai 0. Nilainya ditampilkan sebagai
batang sinyal:

| dBm | Tampilan |
|---|---|
| ≥ −55 | 4 batang, kuat |
| −56 … −67 | 3 batang, baik |
| −68 … −75 | 2 batang, cukup |
| −76 … −85 | 1 batang, lemah |
| < −85 | 1 batang, sangat lemah |

Hasilnya muncul read-only di form **Power Meter Device** (panel *Informasi
Perangkat*, hanya saat mengubah data) dan sebagai kolom di **Monitoring →
Status Perangkat**. Tidak ada input manual — satu-satunya sumbernya endpoint ini.

Bila `stand_lwbp`/`stand_wbp` ikut dikirim, keduanya dikali rasio CT persis
seperti pada `/readings`, supaya angkanya bisa dibandingkan langsung. Tetap saja
angka ini **tidak dipakai menagih** — tagihan hanya membaca `meter_readings`.

### Jenis sambungan (1 phase / 3 phase)

Kolom `phase` pada power meter dipilih manual saat mendaftarkan perangkat,
default **3 phase**. Pengaruhnya murni ke tampilan:

- Meter 1 phase hanya menampilkan tegangan dan arus jalur **R**. Kolom S dan T
  disembunyikan di kartu Real-time Monitoring, laporan data mentah, dan export
  Excel-nya — karena jalur itu memang tidak ada, bukan karena datanya gagal
  masuk.
- Meter 3 phase menampilkan R, S, dan T.

Perhitungan kWh sama sekali tidak melihat kolom ini: tagihan dihitung dari stand
LWBP/WBP, bukan dari tegangan atau arus.

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

| Perintah                  | Jadwal              | Fungsi                                          |
| ------------------------- | ------------------- | ----------------------------------------------- |
| `readings:aggregate`      | tiap jam            | Meringkas pembacaan menjadi agregat harian      |
| `invoices:generate`       | harian, jam setting | Menerbitkan invoice pelanggan yang jatuh tempo  |
| `invoices:mark-overdue`   | harian 01:00        | Menandai invoice lewat jatuh tempo              |
| `readings:prune`          | mingguan            | Menghapus pembacaan mentah di luar masa retensi |

Di Docker, ketiganya dijalankan service **`scheduler`** (`schedule:work`) yang
sudah ada di `docker-compose.yml`. Untuk development lokal jalankan sendiri:

```bash
php artisan schedule:work
```

> Hanya boleh ada **satu** container scheduler. Dua scheduler berarti
> `invoices:generate` jalan dua kali pada tanggal cut-off yang sama.

Pengiriman email invoice lewat antrean ditangani service **`queue`**
(`queue:work`). Tanpa container itu, email otomatis tidak akan terkirim —
job-nya menumpuk di tabel `jobs`.

## Laporan data meter mentah

**Report → Data Meter Mentah** menampilkan pembacaan asli dari gateway, dipakai
saat menelusuri angka tagihan yang dianggap janggal atau memeriksa gateway yang
datanya bolong.

Selalu terikat pada **satu meter + rentang tanggal** — tabel `meter_readings`
bisa berisi jutaan baris, jadi membacanya lintas meter tanpa batas akan
menghabiskan memori.

Dua jenis masalah ditandai otomatis:

| Tanda | Arti |
| ----- | ---- |
| **Stand mundur** | Stand kumulatif turun — meter di-reset atau berputar ke nol. Pemakaiannya tetap terhitung (lihat [Penanganan meter yang di-reset](#penanganan-meter-yang-di-reset)); tanda ini hanya mengabarkan kejadiannya |
| **Jeda N mnt** | Selisih waktu melebihi 3× interval push (minimal 5 menit) — gateway sempat mati atau kehilangan jaringan |

Ambangnya sengaja tidak seketat kelipatan interval saja: dengan push tiap 60
detik, satu-dua push telat karena jaringan sudah cukup menandai hampir semua
baris, dan tabel penuh sorotan merah justru menyembunyikan gangguan sungguhan.

Kolom Δ LWBP / Δ WBP menunjukkan selisih terhadap baris sebelumnya. Baris
pertama tiap halaman tetap dibandingkan dengan pembacaan sebelum halaman itu,
sehingga masalah di batas halaman tidak terlewat.

Kolom tegangan dan arus mengikuti jenis sambungan meter: 3 phase menampilkan
R/S/T, 1 phase hanya R. Export Excel-nya memakai kolom yang sama persis.

Export tersedia dalam Excel saja (maksimal 50.000 baris) — ribuan baris tidak
terbaca di PDF. Penyaring "hanya baris bermasalah" bekerja per halaman; untuk
pemeriksaan menyeluruh gunakan export.

## Penerbitan invoice otomatis

Secara default invoice hasil generate berhenti sebagai **draft** agar sempat
diperiksa. Di **Setting Aplikasi → Billing & Invoice → Otomatisasi** ada dua
saklar:

| Setelan | Efek |
| ------- | ---- |
| Terbitkan invoice otomatis | Status langsung `issued`, tidak perlu klik Terbitkan |
| Kirim email otomatis | Setelah terbit, invoice + PDF diantrekan ke email pelanggan |

Alur penuh saat keduanya menyala: scheduler memanggil `invoices:generate` →
invoice dibuat dan diterbitkan → email masuk antrean → container `queue`
mengirimnya.

**Dua kasus tetap berhenti sebagai draft walau auto-issue menyala**, karena
angkanya hampir pasti salah dan tidak boleh ditagihkan tanpa dilihat manusia:

1. Meter tidak mengirim satu pun pembacaan sepanjang periode
2. Stand meter mundur — meter di-reset atau angkanya berputar ke nol

Keduanya diberi catatan pada invoice dan muncul di daftar sebagai draft.
Pelanggan tanpa alamat email dilewati pengirimannya; invoice-nya tetap sah dan
kegagalan tercatat di hasil generate.

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
