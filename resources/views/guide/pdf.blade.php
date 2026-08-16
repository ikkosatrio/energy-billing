@php
    /**
     * Buku Panduan Energy Billing — dokumen PDF.
     *
     * Ditujukan untuk staf baru tanpa latar belakang teknis, jadi bahasanya
     * sengaja dijaga sederhana: kalimat pendek, istilah asing selalu dijelaskan
     * saat pertama muncul, dan setiap tombol dijelaskan bukan cuma "apa
     * namanya" tapi "apa akibatnya kalau ditekan".
     *
     * Tangkapan layar dibaca dari resources/guide/screenshots/ — sengaja di
     * resources, bukan storage, supaya ikut masuk repositori dan dokumennya
     * tetap bergambar di server. Perbarui dengan:
     *   php artisan demo:heartbeat && node scripts/capture-guide-screenshots.mjs
     */
    $shot = fn (string $name) => resource_path("guide/screenshots/{$name}.jpg");
    $has = fn (string $name) => is_file(resource_path("guide/screenshots/{$name}.jpg"));
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Buku Panduan — {{ setting('app_name', 'Energy Billing') }}</title>
  <style>
    @page { margin: 26mm 16mm 20mm 16mm; }

    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1e293b; line-height: 1.65; margin: 0; }

    h1, h2, h3, h4 { color: #0d1b2e; margin: 0; }
    h1 { font-size: 22px; }
    h2 { font-size: 17px; border-bottom: 2px solid #0d1b2e; padding-bottom: 6px; margin: 0 0 14px; }
    h3 { font-size: 13.5px; margin: 18px 0 6px; }
    h4 { font-size: 11.5px; margin: 12px 0 4px; color: #334155; }
    p { margin: 0 0 9px; }
    ul, ol { margin: 0 0 9px; padding-left: 17px; }
    li { margin-bottom: 4px; }
    strong { color: #0f172a; }
    code { font-family: DejaVu Sans Mono, monospace; font-size: 9.5px; background: #f1f5f9; padding: 1px 4px; }

    .page-break { page-break-before: always; }

    /* ── Sampul ─────────────────────────────────────────────────────── */
    .cover { text-align: center; padding-top: 150px; }
    .cover-kicker { font-size: 11px; letter-spacing: 5px; color: #64748b; }
    .cover-title { font-size: 40px; font-weight: bold; color: #0d1b2e; margin: 14px 0 6px; }
    .cover-sub { font-size: 14px; color: #475569; }
    .cover-box { border: 2px solid #0d1b2e; padding: 18px 22px; margin: 46px 60px 0; text-align: left; font-size: 11px; }
    .cover-foot { margin-top: 60px; font-size: 10px; color: #94a3b8; }

    /* ── Blok penekanan ─────────────────────────────────────────────── */
    .note, .warn, .tip {
      border-left: 4px solid; padding: 9px 12px; margin: 11px 0; font-size: 10px;
    }
    .note { border-color: #2563eb; background: #eff6ff; }
    .warn { border-color: #dc2626; background: #fee2e2; }
    .tip  { border-color: #16a34a; background: #dcfce7; }
    .note-title, .warn-title, .tip-title { font-weight: bold; display: block; margin-bottom: 2px; }
    .note-title { color: #1d4ed8; } .warn-title { color: #b91c1c; } .tip-title { color: #15803d; }

    /* ── Tabel ──────────────────────────────────────────────────────── */
    table.t { width: 100%; border-collapse: collapse; margin: 9px 0 13px; font-size: 9.5px; }
    table.t th {
      background: #0d1b2e; color: #fff; text-align: left; padding: 6px 8px;
      font-size: 8.5px; letter-spacing: .5px;
    }
    table.t td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
    table.t tr.alt td { background: #f8fafc; }
    table.t td.c { text-align: center; }
    .yes { color: #15803d; font-weight: bold; }
    .no { color: #cbd5e1; }

    /* ── Tangkapan layar ────────────────────────────────────────────── */
    .shot { margin: 10px 0 6px; }
    .shot img { width: 100%; border: 1px solid #cbd5e1; }
    .shot-cap { font-size: 8.5px; color: #64748b; margin-top: 3px; font-style: italic; }
    .shot-missing {
      border: 2px dashed #cbd5e1; padding: 30px; text-align: center;
      color: #94a3b8; font-size: 10px; margin: 10px 0;
    }

    /* ── Nomor petunjuk ─────────────────────────────────────────────── */
    .steps { margin: 8px 0 12px; }
    .step { margin-bottom: 7px; }
    .step-no {
      display: inline-block; width: 17px; height: 17px; background: #2563eb; color: #fff;
      text-align: center; font-size: 9px; font-weight: bold; margin-right: 5px;
    }

    /* ── Kartu menu ─────────────────────────────────────────────────── */
    .menu-head { background: #f8fafc; border-left: 5px solid #2563eb; padding: 9px 12px; margin: 0 0 10px; }
    .menu-title { font-size: 15px; font-weight: bold; color: #0d1b2e; }
    .menu-who { font-size: 9px; color: #64748b; margin-top: 2px; }

    .foot-note { font-size: 9px; color: #94a3b8; text-align: center; margin-top: 26px; }
  </style>
</head>
<body>

{{-- ══════════════════════════════ SAMPUL ══════════════════════════════ --}}
<div class="cover">
  <div class="cover-kicker">BUKU PANDUAN PENGGUNA</div>
  <div class="cover-title">{{ setting('app_name', 'Energy Billing') }}</div>
  <div class="cover-sub">Sistem Pencatatan Listrik &amp; Penagihan Gudang</div>

  <div class="cover-box">
    <strong>Buku ini untuk siapa?</strong><br>
    Untuk kamu yang baru pertama kali memakai aplikasi ini. Tidak perlu paham
    listrik, tidak perlu paham komputer lebih dari sekadar membuka browser.
    Semua istilah dijelaskan saat pertama kali muncul.<br><br>

    <strong>Cara membacanya</strong><br>
    Kalau kamu benar-benar baru, baca Bagian 1 dan 2 dulu — cuma beberapa
    halaman, tapi setelah itu semua menu terasa masuk akal. Kalau kamu sudah
    terbiasa dan cuma butuh cara mengerjakan sesuatu, langsung lompat ke
    <strong>Bagian 4: Resep Kerja</strong>.
  </div>

  <div class="cover-foot">
    {{ setting('company_name', '') }} · Dokumen dibuat otomatis {{ now()->translatedFormat('d F Y') }}
  </div>
</div>

{{-- ═════════════════════════════ DAFTAR ISI ═══════════════════════════ --}}
<div class="page-break"></div>
<h2>Daftar Isi</h2>

<table class="t">
  <tr><td><strong>Bagian 1 — Kenalan Dulu</strong></td><td>Apa ini, siapa yang pakai, alur kerjanya</td></tr>
  <tr class="alt"><td style="padding-left:20px">Kamus istilah</td><td>kWh, LWBP, WBP, stand meter, dan kawan-kawan</td></tr>
  <tr><td><strong>Bagian 2 — Mengenal Layar</strong></td><td>Sekali paham, semua halaman terasa sama</td></tr>
  <tr class="alt"><td><strong>Bagian 3 — Keliling Menu</strong></td><td>Semua {{ $totalPages }} halaman dijelaskan satu per satu</td></tr>
  <tr><td style="padding-left:20px">Dashboard</td><td>Ringkasan sehari-hari</td></tr>
  <tr class="alt"><td style="padding-left:20px">Monitoring</td><td>Real-time, Energy History, Status Perangkat</td></tr>
  <tr><td style="padding-left:20px">Billing &amp; Invoice</td><td>Daftar Invoice, Periode &amp; Generate, Pembayaran</td></tr>
  <tr class="alt"><td style="padding-left:20px">Master Data</td><td>Pelanggan, Power Meter</td></tr>
  <tr><td style="padding-left:20px">Tarif &amp; Konfigurasi</td><td>Golongan &amp; Tarif, Jadwal WBP/LWBP</td></tr>
  <tr class="alt"><td style="padding-left:20px">Report</td><td>Empat macam laporan</td></tr>
  <tr><td style="padding-left:20px">Sistem</td><td>Setting, User, Role, Log, Hapus Data Uji</td></tr>
  <tr class="alt"><td><strong>Bagian 4 — Resep Kerja</strong></td><td>Langkah demi langkah untuk tugas sehari-hari</td></tr>
  <tr><td><strong>Bagian 5 — Hak Akses</strong></td><td>Siapa boleh apa, dan kenapa tombolmu tidak muncul</td></tr>
  <tr class="alt"><td><strong>Bagian 6 — Kalau Ada Masalah</strong></td><td>Gangguan yang sering terjadi dan cara mengatasinya</td></tr>
</table>

{{-- ═══════════════════════ BAGIAN 1 — KENALAN ═════════════════════════ --}}
<div class="page-break"></div>
<h2>Bagian 1 — Kenalan Dulu</h2>

<h3>Aplikasi ini sebenarnya untuk apa?</h3>

<p>
  Bayangkan sebuah kawasan gudang. Ada banyak penyewa, dan masing-masing memakai
  listrik dalam jumlah berbeda. Setiap bulan pengelola harus menghitung: siapa
  memakai berapa, dan harus bayar berapa.
</p>

<p>
  Dulu itu dikerjakan dengan cara mencatat angka meteran satu per satu, lalu
  dihitung di Excel. Lama, dan gampang salah ketik. Aplikasi ini menggantikan
  pekerjaan itu: <strong>angka meteran masuk sendiri, tagihan dihitung sendiri.</strong>
</p>

<h3>Alur besarnya</h3>

<p>Ada enam tahap, dan hampir semua pekerjaanmu ada di tahap 4 sampai 6:</p>

<table class="t">
  <tr>
    <td style="width:32px;text-align:center"><strong>1</strong></td>
    <td><strong>Meter listrik mencatat</strong><br>
      Alat di panel listrik tiap gudang menghitung pemakaian terus-menerus.</td>
  </tr>
  <tr class="alt">
    <td class="c"><strong>2</strong></td>
    <td><strong>Gateway mengirim</strong><br>
      Sebuah alat kecil mengirim angka itu ke aplikasi lewat internet, otomatis
      setiap beberapa menit. Tidak ada yang perlu kamu tekan.</td>
  </tr>
  <tr>
    <td class="c"><strong>3</strong></td>
    <td><strong>Aplikasi menyimpan &amp; merangkum</strong><br>
      Angka mentah disimpan, lalu dirangkum jadi total harian supaya grafik dan
      laporan cepat dibuka.</td>
  </tr>
  <tr class="alt">
    <td class="c"><strong>4</strong></td>
    <td><strong>Kamu membuat tagihan</strong><br>
      Sekali sebulan, kamu menekan tombol <em>Generate</em>. Aplikasi menghitung
      pemakaian tiap pelanggan dan membuat invoice.</td>
  </tr>
  <tr>
    <td class="c"><strong>5</strong></td>
    <td><strong>Pelanggan membayar</strong><br>
      Kamu mencatat pembayaran yang masuk — satu per satu, atau sekaligus banyak.</td>
  </tr>
  <tr class="alt">
    <td class="c"><strong>6</strong></td>
    <td><strong>Kuitansi dikirim</strong><br>
      Bukti pembayaran dikirim ke pelanggan, bisa manual atau otomatis.</td>
  </tr>
</table>

<div class="note">
  <span class="note-title">Yang perlu diingat</span>
  Tahap 1–3 berjalan sendiri sepanjang hari tanpa campur tangan siapa pun.
  Kalau angkanya tidak muncul, berarti ada gangguan di alat atau jaringan —
  bukan sesuatu yang bisa diperbaiki dengan menekan tombol di aplikasi.
</div>

<h3 class="page-break">Kamus istilah</h3>

<p>Sepuluh kata ini akan sering kamu temui. Sekali paham, sisanya mudah.</p>

<table class="t">
  <tr><td style="width:120px"><strong>kWh</strong></td>
    <td>Satuan jumlah listrik yang dipakai — seperti "liter" untuk air.
      Semakin besar angkanya, semakin banyak listrik terpakai.</td></tr>
  <tr class="alt"><td><strong>kW</strong></td>
    <td>Bukan jumlah, tapi <em>kecepatan</em> pemakaian pada satu saat — seperti
      "berapa deras air mengalir". Dipakai untuk melihat beban puncak.</td></tr>
  <tr><td><strong>Stand meter</strong></td>
    <td>Angka yang tertera di meteran, terus bertambah dan tidak pernah
      dikurangi — seperti odometer mobil. Pemakaian dihitung dari selisih dua
      pembacaan, bukan dari angkanya langsung.</td></tr>
  <tr class="alt"><td><strong>LWBP</strong></td>
    <td><em>Luar Waktu Beban Puncak.</em> Jam-jam biasa, tarifnya lebih murah.
      Di aplikasi ini selalu berwarna <strong>hijau</strong>.</td></tr>
  <tr><td><strong>WBP</strong></td>
    <td><em>Waktu Beban Puncak.</em> Jam sibuk (umumnya sore–malam), tarifnya
      lebih mahal. Selalu berwarna <strong>kuning</strong>.</td></tr>
  <tr class="alt"><td><strong>Golongan tarif</strong></td>
    <td>Kelompok harga listrik. Pelanggan industri dan bisnis punya tarif
      berbeda. Satu pelanggan masuk satu golongan.</td></tr>
  <tr><td><strong>Periode</strong></td>
    <td>Satu bulan tagihan, misalnya "Juli 2026". Semua invoice bulan itu
      berada di dalam satu periode.</td></tr>
  <tr class="alt"><td><strong>Invoice</strong></td>
    <td>Surat tagihan untuk pelanggan. Berisi rincian pemakaian dan jumlah
      yang harus dibayar.</td></tr>
  <tr><td><strong>Kuitansi</strong></td>
    <td>Bukti bahwa uang sudah diterima. Kebalikan dari invoice: invoice
      meminta, kuitansi menyatakan sudah menerima.</td></tr>
  <tr class="alt"><td><strong>Batch</strong></td>
    <td>Satu operasi yang menyentuh banyak data sekaligus, misalnya melunasi
      100 invoice dalam sekali klik. Bisa dibatalkan sebagai satu kesatuan.</td></tr>
</table>

{{-- ═══════════════════ BAGIAN 2 — MENGENAL LAYAR ══════════════════════ --}}
<div class="page-break"></div>
<h2>Bagian 2 — Mengenal Layar</h2>

<p>
  Kabar baiknya: semua halaman di aplikasi ini bentuknya mirip. Begitu kamu
  paham satu, kamu paham semuanya. Mari lihat halaman Dashboard sebagai contoh.
</p>

@if ($has('dashboard'))
  <div class="shot">
    <img src="{{ $shot('dashboard') }}" alt="Dashboard">
    <div class="shot-cap">Halaman Dashboard — tampilan pertama setelah kamu masuk.</div>
  </div>
@endif

<div class="steps">
  <div class="step"><span class="step-no">1</span>
    <strong>Menu di kiri (sidebar).</strong> Daftar semua halaman, dikelompokkan.
    Angka kecil di sebelah nama grup menunjukkan berapa halaman di dalamnya.
    Klik nama grup untuk membukanya.</div>
  <div class="step"><span class="step-no">2</span>
    <strong>Judul halaman di atas.</strong> Memberi tahu kamu sedang di mana,
    dengan keterangan singkat di bawahnya.</div>
  <div class="step"><span class="step-no">3</span>
    <strong>Kartu ringkasan.</strong> Angka-angka penting dalam kotak. Biasanya
    ada di bagian atas halaman, supaya kamu tahu keadaan tanpa membaca tabel.</div>
  <div class="step"><span class="step-no">4</span>
    <strong>Kotak status gateway di kiri bawah.</strong> Memberitahu berapa meter
    yang sedang terhubung. Kalau merah semua, berarti data sedang tidak masuk.</div>
  <div class="step"><span class="step-no">5</span>
    <strong>Nama kamu di pojok kiri bawah.</strong> Di sebelahnya ada tombol
    keluar.</div>
</div>

<h3>Hal-hal yang muncul di banyak halaman</h3>

<table class="t">
  <tr><td style="width:130px"><strong>Kotak pencarian</strong></td>
    <td>Ketik apa saja — nama pelanggan, nomor invoice, kode meter. Tabel di
      bawahnya menyaring sendiri sambil kamu mengetik. Tidak perlu tekan Enter.</td></tr>
  <tr class="alt"><td><strong>Filter</strong></td>
    <td>Pilihan untuk mempersempit daftar, misalnya hanya periode tertentu atau
      hanya status tertentu. Isinya langsung berubah begitu kamu memilih.</td></tr>
  <tr><td><strong>Badge status</strong></td>
    <td>Label kecil berwarna. Hijau = beres, kuning = perlu perhatian,
      merah = bermasalah, abu-abu = tidak aktif.</td></tr>
  <tr class="alt"><td><strong>Kotak konfirmasi</strong></td>
    <td>Muncul sebelum tindakan yang sulit dibatalkan. Baca dulu isinya —
      kalimatnya menjelaskan apa yang akan terjadi. Tombol merah berarti
      tindakan merusak.</td></tr>
  <tr><td><strong>Notifikasi</strong></td>
    <td>Pesan singkat yang muncul sebentar di pojok setelah kamu menekan
      tombol. Hijau berarti berhasil, merah berarti gagal beserta alasannya.</td></tr>
  <tr class="alt"><td><strong>Tombol PDF / Excel</strong></td>
    <td>Mengunduh isi halaman jadi berkas. PDF untuk dicetak atau dikirim,
      Excel untuk diolah lagi.</td></tr>
</table>

<div class="tip">
  <span class="tip-title">Tidak usah takut salah pencet</span>
  Setiap tindakan yang berbahaya selalu menanyakan konfirmasi lebih dulu.
  Dan hampir semua yang terlanjur terjadi masih bisa ditarik kembali — invoice
  yang dibatalkan bisa dibuka lagi, pembayaran massal bisa ditarik. Yang benar-benar
  permanen cuma penghapusan data, dan itu selalu diberi peringatan merah.
</div>

@php
    /**
     * Isi Bagian 3 disusun sebagai data, bukan ditulis satu per satu sebagai
     * HTML — supaya bentuk tiap halaman panduan pasti seragam dan menambah
     * halaman baru cukup menambah satu entri.
     */
    $menus = [
        [
            'group' => 'Dashboard',
            'pages' => [
                [
                    'title' => 'Dashboard',
                    'who' => 'Semua orang',
                    'shot' => 'dashboard',
                    'what' => 'Halaman pertama setelah masuk. Isinya ringkasan keadaan hari ini: berapa listrik terpakai bulan ini, berapa nilai tagihan berjalan, berapa meter yang aktif, dan berapa uang yang belum dibayar pelanggan.',
                    'see' => [
                        'Empat kartu di atas — angka terpenting yang perlu kamu tahu setiap pagi.',
                        'Kartu tiap meter di bawahnya — kondisi listrik saat ini per gudang.',
                        'Tombol pilihan waktu (5s, 10s, 30s…) untuk mengatur seberapa sering angkanya diperbarui sendiri.',
                    ],
                    'buttons' => [
                        ['Generate Invoice', 'Pintasan ke halaman pembuatan tagihan bulanan.'],
                        ['Real-time penuh →', 'Membuka halaman monitoring yang lebih lengkap.'],
                        ['Ikon putar', 'Memperbarui angka sekarang juga tanpa menunggu.'],
                    ],
                    'gotcha' => 'Kalau semua meter merah bertulisan "Offline", jangan panik dulu. Cek kotak Gateway IoT di kiri bawah — kalau di sana juga 0 terhubung, artinya masalahnya di jaringan atau alat, bukan di aplikasi.',
                ],
            ],
        ],
        [
            'group' => 'Monitoring — melihat listrik yang sedang dipakai',
            'pages' => [
                [
                    'title' => 'Real-time Monitoring',
                    'who' => 'Butuh izin: Lihat monitoring',
                    'shot' => 'monitoring-realtime',
                    'what' => 'Melihat kondisi listrik semua gudang saat ini juga. Halaman ini memperbarui dirinya sendiri, jadi bisa dibiarkan terbuka di layar besar.',
                    'see' => [
                        'Satu kartu per meter, berisi daya yang sedang terpakai, tegangan, dan arus.',
                        'Pemakaian hari ini dan bulan ini, lengkap dengan perkiraan rupiahnya.',
                        'Grafik batang pemakaian harian bulan berjalan.',
                    ],
                    'buttons' => [
                        ['Pilihan jeda (5s–10m / Manual)', 'Mengatur seberapa sering halaman menyegarkan diri. Pilihanmu diingat.'],
                        ['Filter jenis sambungan', 'Menyaring hanya meter 1 phase atau 3 phase saja.'],
                    ],
                    'gotcha' => 'Angka rupiah di sini adalah perkiraan biaya listriknya saja — belum termasuk biaya beban, admin, dan pajak. Nilai tagihan yang sah tetap yang tertulis di invoice.',
                ],
                [
                    'title' => 'Energy History',
                    'who' => 'Butuh izin: Lihat monitoring',
                    'shot' => 'monitoring-history',
                    'what' => 'Melihat riwayat pemakaian satu meter: per jam, per hari, dan per bulan. Berguna untuk menjawab "kenapa tagihan bulan ini naik?".',
                    'see' => [
                        'Grafik per jam untuk satu tanggal yang kamu pilih.',
                        'Grafik dan tabel per hari untuk satu bulan.',
                        'Grafik 12 bulan terakhir dan ringkasan periode.',
                    ],
                    'buttons' => [
                        ['Pilih Power Meter', 'Mengganti meter yang sedang dilihat.'],
                        ['Bulan / Tanggal', 'Mengganti rentang waktu grafiknya.'],
                    ],
                    'gotcha' => 'Batang hijau adalah LWBP dan kuning adalah WBP. Kalau batang kuning terlihat tinggi terus, artinya pelanggan banyak memakai listrik di jam mahal — itu informasi yang layak disampaikan ke mereka.',
                ],
                [
                    'title' => 'Status Perangkat',
                    'who' => 'Butuh izin: Lihat monitoring',
                    'shot' => 'monitoring-devices',
                    'what' => 'Kesehatan alat, bukan angka listriknya. Dipakai untuk memeriksa apakah semua gateway masih mengirim data dengan baik.',
                    'see' => [
                        'Status koneksi tiap perangkat: Online, Offline, atau Maintenance.',
                        'Kekuatan sinyal WiFi dalam bentuk batang — makin banyak batang makin bagus.',
                        'Alamat IP, versi firmware, dan kapan terakhir kali mengirim data.',
                        'Pemakaian LWBP, WBP, dan total hari ini.',
                    ],
                    'buttons' => [
                        ['Filter Status Koneksi', 'Menampilkan hanya yang online, offline, atau maintenance.'],
                    ],
                    'gotcha' => 'Sinyal satu batang berwarna merah biasanya jadi penyebab data bolong-bolong. Kalau ada meter yang sering hilang datanya, cek dulu kolom sinyalnya sebelum menyalahkan alatnya.',
                ],
            ],
        ],
        [
            'group' => 'Billing & Invoice — membuat dan menagih',
            'pages' => [
                [
                    'title' => 'Periode & Generate',
                    'who' => 'Butuh izin: Generate invoice periode',
                    'shot' => 'billing-periods',
                    'what' => 'Tempat membuat tagihan sebulan sekali. Satu tombol di sini menghasilkan invoice untuk semua pelanggan sekaligus.',
                    'see' => [
                        'Pilihan bulan yang akan ditagihkan.',
                        'Peringatan bila ada pelanggan yang datanya belum lengkap.',
                        'Daftar periode sebelumnya beserta statusnya.',
                    ],
                    'buttons' => [
                        ['Generate', 'Membuat invoice untuk semua pelanggan yang siap ditagih. Aman diulang — pelanggan yang sudah punya invoice akan dilewati.'],
                        ['Buat ulang (centang)', 'Menimpa invoice yang masih berstatus draft. Yang sudah terbit tidak ikut tertimpa.'],
                        ['Tutup Periode', 'Mengunci periode supaya tidak bisa digenerate lagi. Lakukan setelah semua beres.'],
                        ['Buka Periode', 'Membuka kembali periode yang tertutup.'],
                    ],
                    'gotcha' => 'Invoice hasil generate berhenti sebagai draft — belum ditagihkan. Kamu masih harus memeriksanya lalu menekan Terbitkan di halaman Daftar Invoice. Ini disengaja, supaya angka yang salah tidak terlanjur sampai ke pelanggan.',
                ],
                [
                    'title' => 'Daftar Invoice',
                    'who' => 'Butuh izin: Lihat invoice',
                    'shot' => 'invoice-list',
                    'what' => 'Semua tagihan yang pernah dibuat. Dari sini kamu menerbitkan, mengirim, menandai lunas, sampai membatalkan.',
                    'see' => [
                        'Kartu ringkasan: berapa belum dibayar, berapa terbayar bulan lalu, berapa draft menunggu terbit.',
                        'Tabel invoice dengan status berwarna.',
                        'Kotak centang di kiri tiap baris untuk pelunasan massal.',
                    ],
                    'buttons' => [
                        ['Klik baris invoice', 'Membuka rincian lengkap beserta tombol tindakannya.'],
                        ['PDF', 'Membuka berkas invoice untuk dicetak atau dikirim.'],
                        ['Tandai Lunas (setelah mencentang)', 'Melunasi banyak invoice sekaligus sebesar sisa tagihan masing-masing.'],
                    ],
                    'gotcha' => 'Kotak centang pada invoice yang masih draft, sudah batal, atau sudah lunas sengaja dimatikan — arahkan kursor ke situ untuk melihat alasannya.',
                ],
                [
                    'title' => 'Rincian Invoice (jendela)',
                    'who' => 'Butuh izin: Lihat invoice',
                    'shot' => 'invoice-detail',
                    'what' => 'Jendela yang terbuka saat kamu mengklik satu baris invoice. Isinya sama persis dengan yang akan dilihat pelanggan di PDF.',
                    'see' => [
                        'Rincian pemakaian LWBP dan WBP beserta stand awal dan akhir.',
                        'Biaya beban, admin, pajak, pembulatan, dan total.',
                        'Riwayat pembayaran bila sudah ada yang masuk.',
                    ],
                    'buttons' => [
                        ['Terbitkan', 'Mengubah draft menjadi tagihan resmi. Hanya muncul untuk invoice draft.'],
                        ['Kirim ke Pelanggan', 'Mengirim invoice ke email pelanggan beserta lampiran PDF.'],
                        ['Unduh PDF', 'Menyimpan berkasnya ke komputermu.'],
                        ['Batalkan', 'Membatalkan tagihan. Nomornya tetap dipakai dan PDF-nya akan bertanda DIBATALKAN.'],
                        ['Buka Kembali', 'Menghidupkan lagi invoice yang batal — kembali jadi draft. Hanya Super Admin.'],
                    ],
                    'gotcha' => 'Invoice yang sudah ada pembayarannya tidak bisa dibatalkan. Hapus dulu pembayarannya, atau kalau memang sudah dibayar sungguhan, uruslah lewat pengembalian dana — bukan dengan membatalkan tagihan.',
                ],
                [
                    'title' => 'Tandai Lunas Massal (jendela)',
                    'who' => 'Butuh izin: Pembayaran massal & impor berkas',
                    'shot' => 'invoice-bulk',
                    'what' => 'Cara cepat melunasi banyak invoice sekaligus. Dipakai saat kamu memegang mutasi rekening dan banyak pelanggan membayar penuh.',
                    'see' => [
                        'Jumlah invoice yang kamu pilih.',
                        'Isian tanggal bayar, metode, dan catatan yang berlaku untuk semuanya.',
                    ],
                    'buttons' => [
                        ['Tandai Lunas', 'Mencatat pembayaran sebesar sisa tagihan untuk setiap invoice terpilih.'],
                    ],
                    'gotcha' => 'Ini hanya untuk pelunasan PENUH. Kalau ada yang bayar sebagian, catat sendiri lewat menu Pembayaran. Setelah selesai akan muncul ringkasan dengan tombol Batalkan — pakai itu kalau ternyata salah pilih.',
                ],
                [
                    'title' => 'Pembayaran',
                    'who' => 'Butuh izin: Lihat pembayaran',
                    'shot' => 'payments',
                    'what' => 'Mencatat uang yang masuk, dan menerbitkan kuitansi untuk pelanggan.',
                    'see' => [
                        'Baris Entri Cepat di atas untuk mencatat satu per satu dengan cepat.',
                        'Tabel semua pembayaran beserta nomor kuitansi dan status kirimnya.',
                        'Tabel Operasi Massal Terakhir untuk menarik kembali batch yang salah.',
                    ],
                    'buttons' => [
                        ['Entri Cepat', 'Ketik nomor invoice, tekan Tab — nominalnya terisi sendiri. Enter untuk simpan.'],
                        ['Catat Pembayaran', 'Bentuk lengkap, bisa unggah bukti transfer dan bayar sebagian.'],
                        ['Impor Berkas', 'Mencatat banyak pembayaran sekaligus dari berkas Excel.'],
                        ['Kirim / Kirim Ulang', 'Mengirim kuitansi ke email pelanggan.'],
                        ['Terbitkan (kolom Kuitansi)', 'Memberi nomor kuitansi dan membuka PDF-nya.'],
                        ['Batalkan (tabel batch)', 'Menarik kembali seluruh pembayaran dalam satu operasi massal.'],
                    ],
                    'gotcha' => 'Batch yang kuitansinya sudah terkirim akan bertuliskan "Terkunci" dan tidak bisa dibatalkan biasa. Itu disengaja: dokumennya sudah ada di tangan pelanggan. Hanya pemegang izin khusus yang bisa memaksa, dan pelanggan otomatis dikirimi pemberitahuan.',
                ],
                [
                    'title' => 'Impor Pembayaran (jendela)',
                    'who' => 'Butuh izin: Pembayaran massal & impor berkas',
                    'shot' => 'payment-import',
                    'what' => 'Mencatat puluhan pembayaran sekaligus dari berkas Excel — biasanya hasil salin-tempel dari mutasi rekening bank.',
                    'see' => [
                        'Tombol unduh template berisi contoh pengisian.',
                        'Daftar kolom yang harus ada.',
                        'Setelah diperiksa: tabel hasil per baris, mana yang siap dan mana yang bermasalah.',
                    ],
                    'buttons' => [
                        ['Unduh Template', 'Mengambil berkas contoh untuk diisi.'],
                        ['Periksa Berkas', 'Membaca dan memeriksa isinya. BELUM menyimpan apa pun.'],
                        ['Simpan N Pembayaran', 'Menyimpan baris-baris yang lolos pemeriksaan.'],
                    ],
                    'gotcha' => 'Nomor invoice wajib diisi di setiap baris. Kolom nama pelanggan boleh kosong, tapi kalau diisi dan ternyata tidak cocok dengan pemilik invoice, barisnya akan ditolak — ini pengaman supaya baris yang tertukar ketahuan sebelum jadi uang.',
                ],
            ],
        ],
        [
            'group' => 'Master Data — data dasar',
            'pages' => [
                [
                    'title' => 'Data Pelanggan',
                    'who' => 'Butuh izin: Lihat pelanggan',
                    'shot' => 'customers',
                    'what' => 'Daftar penyewa gudang. Di sinilah kamu menghubungkan pelanggan dengan meter dan golongan tarifnya.',
                    'see' => [
                        'Kode, nama, alamat, dan orang yang bisa dihubungi.',
                        'Meter yang dipakai dan golongan tarifnya.',
                        'Daya kVA, biaya beban, dan tanggal tagih.',
                    ],
                    'buttons' => [
                        ['Tambah Pelanggan', 'Membuka bentuk isian pelanggan baru.'],
                        ['Ubah', 'Menyunting data pelanggan.'],
                        ['Hapus', 'Menghapus pelanggan. Ditolak bila sudah pernah punya invoice.'],
                    ],
                    'gotcha' => 'Pelanggan hanya bisa ditagih kalau tiga hal terpenuhi: statusnya aktif, sudah punya meter, dan sudah punya golongan tarif. Kalau salah satu kosong, dia akan dilewati saat generate tanpa pemberitahuan khusus.',
                ],
                [
                    'title' => 'Bentuk Isian Pelanggan (jendela)',
                    'who' => 'Butuh izin: Tambah / Ubah pelanggan',
                    'shot' => 'customer-form',
                    'what' => 'Isian data pelanggan. Kolom bertanda bintang merah wajib diisi.',
                    'see' => [
                        'Identitas: kode, nama, alamat, PIC, telepon, email.',
                        'Kelistrikan: meter, golongan tarif, daya kVA.',
                        'Penagihan: cara hitung biaya beban dan tanggal tagih.',
                    ],
                    'buttons' => [
                        ['Simpan', 'Menyimpan dan menutup jendela.'],
                        ['Batal', 'Menutup tanpa menyimpan.'],
                    ],
                    'gotcha' => 'Biaya beban punya dua cara hitung. "Flat" berarti nominal tetap tiap bulan. "Per kVA" berarti dihitung dari daya dikali tarif golongan. Pilih salah satu — kalau memilih per kVA, kolom nominal tetapnya akan diabaikan.',
                ],
                [
                    'title' => 'Power Meter Device',
                    'who' => 'Butuh izin: Lihat power meter',
                    'shot' => 'meters',
                    'what' => 'Daftar alat meteran yang terpasang. Satu meter dipakai satu pelanggan.',
                    'see' => [
                        'ID Meter — angka yang dipakai gateway saat mengirim data.',
                        'Merek, model, jenis sambungan, lokasi.',
                        'Kekuatan sinyal dan stand terakhir (LWBP dan WBP terpisah).',
                    ],
                    'buttons' => [
                        ['Tambah Perangkat', 'Mendaftarkan meter baru.'],
                        ['Ubah / Hapus', 'Menyunting atau menghapus meter.'],
                    ],
                    'gotcha' => 'Kolom ID Meter itu penting: angka itulah yang harus dipakai teknisi saat menyetel gateway. Kalau salah, data akan masuk ke gudang orang lain.',
                ],
            ],
        ],
        [
            'group' => 'Tarif & Konfigurasi — harga dan jam',
            'pages' => [
                [
                    'title' => 'Golongan & Tarif',
                    'who' => 'Butuh izin: Lihat golongan & tarif',
                    'shot' => 'tariff-groups',
                    'what' => 'Mengatur harga listrik per kWh untuk tiap kelompok pelanggan.',
                    'see' => [
                        'Daftar golongan beserta tarif yang sedang berlaku.',
                        'Riwayat perubahan tarif.',
                    ],
                    'buttons' => [
                        ['Tambah Golongan', 'Membuat kelompok tarif baru.'],
                        ['Tarif Baru', 'Memasukkan tarif yang berlaku mulai tanggal tertentu.'],
                        ['Riwayat', 'Melihat tarif-tarif lama.'],
                    ],
                    'gotcha' => 'Menaikkan tarif TIDAK dilakukan dengan mengubah angka yang ada, melainkan dengan menambah tarif baru berikut tanggal berlakunya. Dengan begitu invoice lama tetap memakai tarif lama dan angkanya tidak berubah di kemudian hari.',
                ],
                [
                    'title' => 'Jadwal WBP / LWBP',
                    'who' => 'Butuh izin: Lihat golongan & tarif',
                    'shot' => 'tariff-schedules',
                    'what' => 'Mengatur jam berapa saja yang dihitung sebagai jam mahal (WBP) untuk tiap meter.',
                    'see' => [
                        'Daftar periode waktu beserta jenis tarifnya.',
                        'Pita 24 jam berwarna sebagai gambaran cepat.',
                        'Daftar aturan dan pemeriksaan kesalahan.',
                    ],
                    'buttons' => [
                        ['Tambah Periode', 'Menambah satu baris waktu.'],
                        ['Duplikat dari…', 'Menyalin jadwal dari meter lain — jauh lebih cepat daripada mengisi ulang.'],
                        ['Simpan Jadwal', 'Menyimpan perubahan.'],
                    ],
                    'gotcha' => 'Jadwal ini TIDAK memengaruhi perhitungan tagihan. Meter sudah mengirim angka LWBP dan WBP secara terpisah. Jadwal di sini hanya untuk tampilan aplikasi dan sebagai acuan saat mencocokkan dengan setelan di alat.',
                ],
            ],
        ],
        [
            'group' => 'Report — laporan',
            'pages' => [
                [
                    'title' => 'Rekap Pemakaian kWh',
                    'who' => 'Butuh izin: Lihat report',
                    'shot' => 'report-usage',
                    'what' => 'Berapa kWh yang dipakai tiap pelanggan dalam rentang tanggal pilihanmu.',
                    'see' => ['Pemakaian LWBP, WBP, total, beban puncak, dan nilai tagihannya.'],
                    'buttons' => [
                        ['Excel / PDF', 'Mengunduh laporan.'],
                        ['Filter tanggal & pelanggan', 'Mempersempit isi laporan.'],
                    ],
                    'gotcha' => 'Laporan ini dihitung dari rangkuman harian, bukan dari invoice. Jadi bisa dibuat untuk bulan yang belum ditagihkan sekalipun.',
                ],
                [
                    'title' => 'Rekap Tagihan & Penerimaan',
                    'who' => 'Butuh izin: Lihat report',
                    'shot' => 'report-billing',
                    'what' => 'Sisi uang: berapa yang ditagihkan, berapa yang sudah masuk, berapa yang masih kurang.',
                    'see' => ['Satu baris per invoice beserta status dan sisa tagihannya.'],
                    'buttons' => [['Excel / PDF', 'Mengunduh laporan.']],
                    'gotcha' => 'Invoice yang dibatalkan tidak ikut dihitung di sini — memang tidak boleh, karena bukan tagihan yang berlaku.',
                ],
                [
                    'title' => 'Laporan Pembayaran',
                    'who' => 'Butuh izin: Lihat report',
                    'shot' => 'report-payments',
                    'what' => 'Satu baris per transaksi pembayaran, plus ringkasan tunggakan berdasarkan umurnya.',
                    'see' => [
                        'Tiap pembayaran beserta metode dan asalnya (manual, massal, impor).',
                        'Pengelompokan tunggakan: belum jatuh tempo, 1–30 hari, 31–60 hari, di atas 60 hari.',
                    ],
                    'buttons' => [['Filter metode & pelanggan', 'Mempersempit isi laporan.']],
                    'gotcha' => 'Kolom asal berguna saat menelusuri kesalahan: pembayaran bertanda "Massal" atau "Impor" datang dari operasi banyak sekaligus, jadi kalau satu salah, kemungkinan besar yang lain juga.',
                ],
                [
                    'title' => 'Data Meter Mentah',
                    'who' => 'Butuh izin: Lihat report',
                    'shot' => 'report-readings',
                    'what' => 'Angka apa adanya yang dikirim meter, baris demi baris. Dipakai saat ada yang perlu ditelusuri sampai ke akarnya.',
                    'see' => [
                        'Tiap pembacaan beserta stand, selisih, tegangan, dan arus.',
                        'Baris bermasalah ditandai merah: stand mundur atau ada jeda data.',
                        'Kotak retensi: sampai kapan data mentah disimpan.',
                    ],
                    'buttons' => [
                        ['Hanya baris bermasalah (centang)', 'Menyaring agar yang normal disembunyikan.'],
                        ['Excel', 'Mengunduh data mentah.'],
                        ['Hapus Sekarang', 'Membuang data lama di luar jadwal otomatis. Butuh izin khusus.'],
                    ],
                    'gotcha' => 'Data mentah dibuang otomatis setelah masa retensi habis, tapi rangkuman hariannya tetap disimpan. Jadi laporan dan tagihan lama tidak ikut hilang.',
                ],
            ],
        ],
        [
            'group' => 'Sistem — pengaturan',
            'pages' => [
                [
                    'title' => 'Setting Aplikasi',
                    'who' => 'Butuh izin: Kelola setting aplikasi',
                    'shot' => 'settings',
                    'what' => 'Pusat pengaturan: identitas perusahaan, aturan penagihan, kuitansi, dan sambungan alat.',
                    'see' => [
                        'Identitas: nama, alamat, logo, NPWP.',
                        'Penagihan: tanggal generate, jatuh tempo, format nomor, pajak, pembulatan.',
                        'Kuitansi: format nomor dan pengiriman otomatis.',
                        'IoT: interval kirim, ambang offline, lama penyimpanan data.',
                    ],
                    'buttons' => [
                        ['Simpan', 'Menyimpan semua perubahan.'],
                        ['Generate token', 'Membuat kunci baru untuk gateway.'],
                    ],
                    'gotcha' => 'Mengganti token API akan memutus semua gateway sampai teknisi memasukkan token yang baru. Jangan lakukan tanpa berkoordinasi.',
                ],
                [
                    'title' => 'User Management',
                    'who' => 'Butuh izin: Lihat user',
                    'shot' => 'users',
                    'what' => 'Mengelola siapa saja yang boleh masuk ke aplikasi.',
                    'see' => ['Daftar pengguna beserta perannya dan status aktifnya.'],
                    'buttons' => [
                        ['Tambah User', 'Membuat akun baru.'],
                        ['Ubah / Hapus', 'Menyunting atau menghapus akun.'],
                    ],
                    'gotcha' => 'Menonaktifkan akun lebih baik daripada menghapus. Menghapus akan memutus jejak siapa yang dulu mengerjakan apa.',
                ],
                [
                    'title' => 'Role & Hak Akses',
                    'who' => 'Butuh izin: Lihat role',
                    'shot' => 'roles',
                    'what' => 'Mengatur peran dan apa saja yang boleh dilakukan tiap peran.',
                    'see' => ['Daftar peran, dan daftar centang izin per kelompok.'],
                    'buttons' => [
                        ['Tambah Role', 'Membuat peran baru.'],
                        ['Ubah', 'Menyunting centang izinnya.'],
                    ],
                    'gotcha' => 'Super Admin selalu punya semua izin, apa pun yang tercentang di sana. Itu memang disengaja supaya tidak ada kemungkinan mengunci diri sendiri.',
                ],
                [
                    'title' => 'Log Aktivitas',
                    'who' => 'Butuh izin: Lihat log aktivitas',
                    'shot' => 'activity-logs',
                    'what' => 'Catatan siapa melakukan apa dan kapan. Dipakai saat ada yang perlu ditelusuri.',
                    'see' => ['Waktu, pengguna, tindakan, dan keterangannya.'],
                    'buttons' => [['Filter', 'Menyaring berdasarkan pengguna atau jenis tindakan.']],
                    'gotcha' => 'Catatan ini tidak bisa disunting siapa pun, termasuk Super Admin. Memang begitu seharusnya.',
                ],
                [
                    'title' => 'Hapus Data Uji Coba',
                    'who' => 'Butuh izin khusus: Hapus data mentah & agregat',
                    'shot' => 'trial-data',
                    'what' => 'Membersihkan data pembacaan pada rentang tanggal tertentu. Dipakai setelah masa uji coba, sebelum sistem dipakai sungguhan.',
                    'see' => ['Pilihan rentang tanggal dan jumlah baris yang akan terhapus.'],
                    'buttons' => [['Hapus', 'Membuang data pada rentang itu secara permanen.']],
                    'gotcha' => 'Ini permanen dan tidak bisa dibatalkan. Pastikan rentang tanggalnya benar, dan pastikan tidak ada invoice yang bergantung pada data itu.',
                ],
            ],
        ],
    ];
@endphp

{{-- ═══════════════════ BAGIAN 3 — KELILING MENU ═══════════════════════ --}}
<div class="page-break"></div>
<h2>Bagian 3 — Keliling Menu</h2>

<p>
  Setiap halaman dijelaskan dengan susunan yang sama: <strong>untuk apa</strong>,
  <strong>apa yang kamu lihat</strong>, <strong>tombolnya apa saja</strong>, dan
  <strong>hal yang sering bikin bingung</strong>. Bagian terakhir itu yang paling
  layak dibaca — isinya hal-hal yang biasanya baru ketahuan setelah salah.
</p>

@foreach ($menus as $group)
  <div class="page-break"></div>
  <h2>{{ $group['group'] }}</h2>

  @foreach ($group['pages'] as $i => $page)
    @if ($i > 0)<div class="page-break"></div>@endif

    <div class="menu-head">
      <div class="menu-title">{{ $page['title'] }}</div>
      <div class="menu-who">{{ $page['who'] }}</div>
    </div>

    <p>{{ $page['what'] }}</p>

    @if ($has($page['shot']))
      <div class="shot">
        <img src="{{ $shot($page['shot']) }}" alt="{{ $page['title'] }}">
        <div class="shot-cap">Halaman {{ $page['title'] }}</div>
      </div>
    @else
      <div class="shot-missing">[ Tangkapan layar {{ $page['shot'] }} belum tersedia ]</div>
    @endif

    <h4>Apa yang kamu lihat</h4>
    <ul>
      @foreach ($page['see'] as $item)
        <li>{{ $item }}</li>
      @endforeach
    </ul>

    <h4>Tombol dan akibatnya</h4>
    <table class="t">
      @foreach ($page['buttons'] as $n => $btn)
        <tr class="{{ $n % 2 ? 'alt' : '' }}">
          <td style="width:150px"><strong>{{ $btn[0] }}</strong></td>
          <td>{{ $btn[1] }}</td>
        </tr>
      @endforeach
    </table>

    <div class="warn">
      <span class="warn-title">Yang sering bikin bingung</span>
      {{ $page['gotcha'] }}
    </div>
  @endforeach
@endforeach

{{-- ═══════════════════ BAGIAN 4 — RESEP KERJA ═════════════════════════ --}}
<div class="page-break"></div>
<h2>Bagian 4 — Resep Kerja</h2>

<p>
  Bagian ini bisa langsung diikuti tanpa membaca yang lain. Tiap resep berisi
  langkah berurutan untuk satu tugas nyata.
</p>

@php
    $recipes = [
        [
            'title' => 'Rutin bulanan: dari tagihan sampai lunas',
            'when' => 'Setiap awal bulan, untuk pemakaian bulan sebelumnya.',
            'steps' => [
                'Buka <strong>Billing &amp; Invoice → Periode &amp; Generate</strong>. Pilih bulan yang mau ditagihkan.',
                'Baca peringatan kuning bila ada — itu daftar pelanggan yang datanya belum lengkap. Perbaiki dulu kalau memungkinkan.',
                'Tekan <strong>Generate</strong>. Tunggu sampai muncul ringkasan berapa invoice dibuat.',
                'Pindah ke <strong>Daftar Invoice</strong>. Semua masih berstatus Draft.',
                'Klik beberapa invoice, periksa angkanya masuk akal. Bandingkan dengan bulan lalu bila ragu.',
                'Buka satu per satu lalu tekan <strong>Terbitkan</strong>. Setelah terbit, tekan <strong>Kirim ke Pelanggan</strong>.',
                'Tunggu pembayaran masuk. Cek mutasi rekening secara berkala.',
                'Catat pembayaran — lihat resep berikutnya untuk cara cepatnya.',
                'Setelah semua beres, kembali ke Periode &amp; Generate lalu tekan <strong>Tutup Periode</strong>.',
            ],
        ],
        [
            'title' => 'Menagih banyak pembayaran sekaligus',
            'when' => 'Saat kamu memegang mutasi rekening berisi banyak transfer.',
            'steps' => [
                'Kalau semuanya bayar PENUH: buka <strong>Daftar Invoice</strong>, centang yang sudah bayar, tekan <strong>Tandai Lunas</strong>. Selesai.',
                'Kalau ada yang bayar sebagian atau jumlahnya banyak sekali: buka <strong>Pembayaran → Impor Berkas</strong>.',
                'Tekan <strong>Unduh Template</strong>, lalu isi dengan menyalin dari mutasi bank.',
                'Pastikan kolom <code>no_invoice</code> terisi di setiap baris — ini wajib.',
                'Unggah berkasnya lalu tekan <strong>Periksa Berkas</strong>. Belum ada yang tersimpan pada tahap ini.',
                'Baca tabel hasilnya. Baris merah berisi alasan penolakan — perbaiki di berkas lalu ulangi bila perlu.',
                'Kalau sudah benar, tekan <strong>Simpan N Pembayaran</strong>.',
            ],
        ],
        [
            'title' => 'Salah catat pembayaran',
            'when' => 'Ketahuan salah setelah tersimpan.',
            'steps' => [
                'Kalau salahnya satu pembayaran saja: buka <strong>Pembayaran</strong>, cari barisnya, tekan <strong>Hapus</strong>. Status invoice otomatis kembali.',
                'Kalau salahnya satu operasi massal: di tabel <strong>Operasi Massal Terakhir</strong>, tekan <strong>Batalkan</strong> pada batch yang bersangkutan.',
                'Kalau tombolnya bertuliskan <strong>Terkunci</strong>, berarti kuitansinya sudah terkirim ke pelanggan. Minta bantuan Super Admin.',
                'Super Admin bisa menekan <strong>Batalkan Paksa</strong> dan mengisi alasannya — pelanggan otomatis dikirimi pemberitahuan.',
            ],
        ],
        [
            'title' => 'Invoice salah dan harus dibatalkan',
            'when' => 'Angka keliru, atau tagihan seharusnya tidak terbit.',
            'steps' => [
                'Buka <strong>Daftar Invoice</strong>, klik invoice yang bermasalah.',
                'Tekan <strong>Batalkan</strong>, isi alasannya (boleh dikosongkan, tapi sebaiknya diisi).',
                'Nomor invoice tetap terpakai dan PDF-nya kini bertanda DIBATALKAN — itu memang seharusnya, bukan kesalahan.',
                'Kalau ternyata pembatalannya yang keliru: Super Admin bisa menekan <strong>Buka Kembali</strong>. Invoice kembali jadi draft dan harus diterbitkan ulang.',
            ],
        ],
        [
            'title' => 'Pelanggan baru masuk',
            'when' => 'Ada penyewa gudang baru.',
            'steps' => [
                'Pastikan meternya sudah terdaftar di <strong>Master Data → Power Meter Device</strong>. Kalau belum, tambahkan dulu.',
                'Catat <strong>ID Meter</strong>-nya dan berikan ke teknisi untuk menyetel gateway.',
                'Buka <strong>Master Data → Data Pelanggan</strong>, tekan <strong>Tambah Pelanggan</strong>.',
                'Isi identitas, pilih meter, pilih golongan tarif, isi daya kVA dan biaya beban.',
                'Isi email — tanpa email, invoice dan kuitansi tidak bisa dikirim otomatis.',
                'Cek di <strong>Monitoring → Status Perangkat</strong> apakah data sudah masuk. Kalau belum, koordinasikan dengan teknisi.',
                'Atur jadwal WBP/LWBP meternya lewat <strong>Duplikat dari…</strong> agar cepat.',
            ],
        ],
        [
            'title' => 'Tarif listrik naik',
            'when' => 'Ada penyesuaian harga dari PLN atau kebijakan pengelola.',
            'steps' => [
                'Buka <strong>Tarif &amp; Konfigurasi → Golongan &amp; Tarif</strong>.',
                'Pada golongan yang berubah, tekan <strong>Tarif Baru</strong>.',
                'Isi tarif LWBP dan WBP yang baru, lalu isi tanggal mulai berlakunya.',
                'Simpan. Tarif lama otomatis ditutup pada hari sebelumnya.',
                'Jangan mengubah angka pada tarif lama — invoice yang sudah terbit memakai angka itu.',
            ],
        ],
    ];
@endphp

@foreach ($recipes as $r)
  <h3>{{ $r['title'] }}</h3>
  <p style="font-size:9.5px;color:#64748b"><em>Kapan dipakai: {{ $r['when'] }}</em></p>
  <ol>
    @foreach ($r['steps'] as $s)
      <li>{!! $s !!}</li>
    @endforeach
  </ol>
@endforeach

{{-- ═══════════════════ BAGIAN 5 — HAK AKSES ═══════════════════════════ --}}
<div class="page-break"></div>
<h2>Bagian 5 — Hak Akses</h2>

<p>
  Tidak semua orang boleh melakukan semua hal. Aplikasi ini memakai
  <strong>peran</strong> (role): satu peran berisi sekumpulan izin, dan setiap
  pengguna mendapat satu peran.
</p>

<h3>Empat peran bawaan</h3>

<table class="t">
  <tr><th style="width:110px">Peran</th><th>Untuk siapa, dan bisa apa</th></tr>
  @foreach ($roles as $n => $role)
    <tr class="{{ $n % 2 ? 'alt' : '' }}">
      <td><strong>{{ $role->name }}</strong></td>
      <td>{{ $role->description }}</td>
    </tr>
  @endforeach
</table>

<div class="note">
  <span class="note-title">Kenapa tombolku tidak muncul?</span>
  Karena peranmu tidak punya izin untuk itu. Aplikasi ini menyembunyikan tombol
  yang tidak boleh kamu pakai, bukan menampilkannya lalu menolak saat ditekan.
  Jadi kalau ada tombol yang disebut di buku ini tapi tidak kamu temukan di
  layar, itu bukan kerusakan — mintalah izinnya ke Super Admin.
</div>

<h3>Daftar lengkap izin</h3>

<p style="font-size:9.5px;color:#64748b">
  Tanda centang berarti peran itu memilikinya. Super Admin sengaja tidak
  ditampilkan karena selalu punya semuanya.
</p>

@foreach ($permissionGroups as $groupName => $items)
  <h4>{{ $groupName }}</h4>
  <table class="t">
    <tr>
      <th>Izin</th>
      @foreach ($nonSuperRoles as $role)
        <th style="width:66px;text-align:center">{{ $role->name }}</th>
      @endforeach
    </tr>
    @foreach ($items as $n => $permission)
      <tr class="{{ $n % 2 ? 'alt' : '' }}">
        <td>{{ $permission->name }}</td>
        @foreach ($nonSuperRoles as $role)
          <td class="c">
            @if ($role->permissions->contains('id', $permission->id))
              <span class="yes">&#10003;</span>
            @else
              <span class="no">&ndash;</span>
            @endif
          </td>
        @endforeach
      </tr>
    @endforeach
  </table>
@endforeach

{{-- ═══════════════ BAGIAN 6 — KALAU ADA MASALAH ═══════════════════════ --}}
<div class="page-break"></div>
<h2>Bagian 6 — Kalau Ada Masalah</h2>

<table class="t">
  <tr><th style="width:180px">Gejala</th><th>Kemungkinan sebab &amp; apa yang bisa kamu lakukan</th></tr>

  <tr>
    <td><strong>Semua meter merah "Offline"</strong></td>
    <td>Cek kotak Gateway IoT di kiri bawah. Kalau di sana juga nol, gangguannya
      di jaringan atau listrik lokasi — hubungi teknisi. Aplikasi tidak bisa
      memperbaikinya dari sini.</td>
  </tr>
  <tr class="alt">
    <td><strong>Satu meter saja yang offline</strong></td>
    <td>Buka <strong>Status Perangkat</strong>, lihat kolom sinyal. Sinyal satu
      batang merah biasanya penyebabnya. Catat kapan terakhir kirim, lalu
      teruskan ke teknisi.</td>
  </tr>
  <tr>
    <td><strong>Data bolong-bolong</strong></td>
    <td>Buka <strong>Data Meter Mentah</strong>, centang "Hanya baris bermasalah".
      Jeda data berarti gateway sempat putus. Tagihan tetap benar karena dihitung
      dari selisih stand awal dan akhir, bukan penjumlahan tiap baris.</td>
  </tr>
  <tr class="alt">
    <td><strong>Pelanggan tidak dapat invoice</strong></td>
    <td>Cek tiga hal di Data Pelanggan: statusnya aktif, sudah punya meter, sudah
      punya golongan tarif. Kalau salah satu kosong, dia dilewati saat generate.</td>
  </tr>
  <tr>
    <td><strong>Email gagal terkirim</strong></td>
    <td>Paling sering karena pelanggan belum punya alamat email. Isi dulu di
      Data Pelanggan. Kalau emailnya sudah ada tapi tetap gagal, laporkan pesan
      merah yang muncul ke pengelola sistem.</td>
  </tr>
  <tr class="alt">
    <td><strong>Angka tagihan terasa aneh</strong></td>
    <td>Buka invoicenya, lihat stand awal dan stand akhir. Kalau stand akhir
      lebih kecil dari stand awal, meter sempat di-reset — invoice biasanya sudah
      diberi catatan otomatis soal ini.</td>
  </tr>
  <tr>
    <td><strong>Tombol yang dicari tidak ada</strong></td>
    <td>Kemungkinan besar peranmu tidak punya izinnya. Lihat Bagian 5.</td>
  </tr>
  <tr class="alt">
    <td><strong>Tidak bisa generate ulang</strong></td>
    <td>Periodenya sudah ditutup. Buka lagi lewat Periode &amp; Generate, tapi
      pastikan dulu memang perlu.</td>
  </tr>
</table>

<div class="tip">
  <span class="tip-title">Sebelum melapor</span>
  Catat tiga hal ini — akan sangat mempercepat penanganan: <strong>di halaman mana</strong>,
  <strong>tombol apa yang kamu tekan</strong>, dan <strong>pesan apa yang muncul</strong>
  (kalau ada, potret layarnya).
</div>

<div class="foot-note">
  Buku Panduan {{ setting('app_name', 'Energy Billing') }} —
  dibuat otomatis dari aplikasi pada {{ now()->translatedFormat('d F Y, H:i') }} WIB.<br>
  Tangkapan layar diambil dari data contoh, sehingga angkanya berbeda dengan data sesungguhnya.
</div>

</body>
</html>
