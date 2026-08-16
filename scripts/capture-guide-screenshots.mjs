/**
 * Memotret setiap halaman aplikasi untuk dipasang di Buku Panduan.
 *
 * Dijalankan manual saat tampilan berubah, BUKAN bagian dari build:
 *
 *   php artisan serve --port=8123          (di terminal lain)
 *   node scripts/capture-guide-screenshots.mjs
 *
 * Hasilnya masuk ke resources/guide/screenshots/ dan dibaca langsung oleh view PDF.
 *
 * Dua pilihan yang sengaja diambil demi ukuran berkas:
 *   - JPEG, bukan PNG.
 *   - deviceScaleFactor 1, bukan 2.
 * Keduanya bukan soal hemat disk, melainkan memori: DomPDF memuat SELURUH
 * gambar ke memori sekaligus, dan pada skala 2 pembuatan PDF-nya menembus
 * batas memori PHP bawaan (128 MB) lalu gagal total. Lebar 1440px sudah jauh
 * lebih dari cukup untuk gambar selebar halaman A4.
 */
import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';

const BASE = process.env.GUIDE_BASE_URL ?? 'http://127.0.0.1:8123';
const USER = process.env.GUIDE_USER ?? 'admin';
const PASS = process.env.GUIDE_PASS ?? 'password';
const OUT = 'resources/guide/screenshots';

/** Lebar viewport dipilih supaya sidebar + tabel muat tanpa scroll mendatar. */
const VIEWPORT = { width: 1440, height: 900 };

/**
 * Daftar bidikan. `action` opsional — dipakai membuka modal atau menekan
 * tombol sebelum dipotret, sehingga dialog penting ikut terdokumentasi.
 */
const SHOTS = [
  { name: 'login', path: '/login', noAuth: true },
  { name: 'dashboard', path: '/dashboard' },

  { name: 'monitoring-realtime', path: '/monitoring/realtime' },
  { name: 'monitoring-history', path: '/monitoring/history' },
  { name: 'monitoring-devices', path: '/monitoring/devices' },

  { name: 'invoice-list', path: '/billing/invoices' },
  {
    name: 'invoice-detail',
    path: '/billing/invoices',
    action: async (page) => {
      await page.locator('table tbody tr.clickable').first().click();
      await page.waitForSelector('.modal', { timeout: 5000 });
    },
  },
  {
    name: 'invoice-bulk',
    path: '/billing/invoices',
    action: async (page) => {
      const box = page.locator('table tbody input[type=checkbox]:not([disabled])').first();
      await box.check();
      await page.getByRole('button', { name: /Tandai Lunas/i }).first().click();
      await page.waitForSelector('.modal', { timeout: 5000 });
    },
  },
  { name: 'billing-periods', path: '/billing/periods' },
  { name: 'payments', path: '/billing/payments' },
  {
    name: 'payment-import',
    path: '/billing/payments',
    action: async (page) => {
      await page.getByRole('button', { name: /Impor Berkas/i }).click();
      await page.waitForSelector('.modal', { timeout: 5000 });
    },
  },

  { name: 'customers', path: '/master/customers' },
  {
    name: 'customer-form',
    path: '/master/customers',
    action: async (page) => {
      await page.getByRole('button', { name: /Tambah Pelanggan/i }).click();
      await page.waitForSelector('.modal', { timeout: 5000 });
    },
  },
  { name: 'meters', path: '/master/meters' },

  { name: 'tariff-groups', path: '/tariff/groups' },
  { name: 'tariff-schedules', path: '/tariff/schedules' },

  { name: 'report-usage', path: '/report/usage' },
  { name: 'report-billing', path: '/report/billing' },
  { name: 'report-payments', path: '/report/payments' },
  { name: 'report-readings', path: '/report/readings' },

  { name: 'settings', path: '/system/settings' },
  { name: 'users', path: '/system/users' },
  { name: 'roles', path: '/system/roles' },
  { name: 'activity-logs', path: '/system/activity-logs' },
  { name: 'trial-data', path: '/system/trial-data' },
];

const browser = await chromium.launch();
const context = await browser.newContext({ viewport: VIEWPORT, deviceScaleFactor: 1, locale: 'id-ID' });
const page = await context.newPage();

await mkdir(OUT, { recursive: true });

// Login sekali; sesinya dipakai untuk seluruh bidikan berikutnya.
await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
await page.fill('input[name=username]', USER);
await page.fill('input[name=password]', PASS);
await Promise.all([page.waitForURL('**/dashboard', { timeout: 15000 }), page.click('button[type=submit]')]);

let ok = 0;
let gagal = 0;

for (const shot of SHOTS) {
  try {
    if (shot.noAuth) {
      // Halaman login hanya bisa dilihat saat belum masuk — dipotret dari
      // konteks terpisah supaya sesi utama tidak ikut hilang.
      const anon = await browser.newContext({ viewport: VIEWPORT, deviceScaleFactor: 1, locale: 'id-ID' });
      const p = await anon.newPage();
      await p.goto(`${BASE}${shot.path}`, { waitUntil: 'networkidle' });
      await p.screenshot({ path: `${OUT}/${shot.name}.jpg`, type: 'jpeg', quality: 82 });
      await anon.close();
      console.log(`  ✓ ${shot.name}`);
      ok++;
      continue;
    }

    await page.goto(`${BASE}${shot.path}`, { waitUntil: 'networkidle' });
    // Livewire memasang isinya setelah dokumen siap; beri waktu render.
    await page.waitForTimeout(900);

    if (shot.action) {
      await shot.action(page);
      await page.waitForTimeout(600);
    }

    await page.screenshot({ path: `${OUT}/${shot.name}.jpg`, type: 'jpeg', quality: 82 });
    console.log(`  ✓ ${shot.name}`);
    ok++;
  } catch (e) {
    console.log(`  ✗ ${shot.name} — ${e.message.split('\n')[0]}`);
    gagal++;
  }
}

await browser.close();
console.log(`\nSelesai: ${ok} berhasil, ${gagal} gagal. Tersimpan di ${OUT}/`);
