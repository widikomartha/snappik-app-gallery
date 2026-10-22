import { expect, test } from '@playwright/test';
import { spawnSync } from 'node:child_process';

function fixture(input: object) {
  const result = spawnSync('php', ['tests/Support/voucher-browser-fixture.php'], {
    input: JSON.stringify(input), encoding: 'utf8', timeout: 30000,
    env: { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'pgsql', DB_DATABASE: 'snappik_test', DB_URL: '', CACHE_STORE: 'array', AUTH_MAILER: 'array' },
  });
  expect(result.status, 'Isolated synthetic voucher fixture').toBe(0);
}

for (const width of [360, 1440]) {
  for (const theme of ['light', 'dark'] as const) {
    test(`owner creates, versions and deactivates vouchers ${width}px ${theme}`, async ({ page }, info) => {
      test.skip(info.project.name !== 'voucher-flow', 'Dedicated test database server only.');
      test.setTimeout(60000);
      await page.setViewportSize({ width, height: 1000 });
      await page.emulateMedia({ colorScheme: theme, reducedMotion: width === 360 ? 'reduce' : 'no-preference' });
      const errors: string[] = [];
      page.on('pageerror', error => errors.push(error.message));
      const suffix = `${Date.now()}-${width}-${theme}`;
      const email = `browser-voucher-${suffix}@example.test`;
      const code = `OWNER-${suffix}`.toUpperCase();
      fixture({ action: 'seed', email });
      await page.goto('/login');
      await page.getByLabel('Email', { exact: true }).fill(email);
      await page.getByLabel('Kata sandi', { exact: true }).fill('BrowserVoucherPassword123');
      await page.getByRole('button', { name: 'Masuk', exact: true }).click();
      await expect(page).toHaveURL(/\/admin$/);
      await page.getByRole('link', { name: 'Kelola voucher', exact: true }).click();
      await page.getByRole('link', { name: 'Buat voucher', exact: true }).click();
      await page.getByLabel('Kode voucher', { exact: true }).fill(`  ${code.toLowerCase()}  `);
      await page.getByLabel('Mulai (WITA, UTC+08:00)', { exact: true }).fill('2026-10-22T00:00');
      await page.getByLabel('Berakhir (WITA, UTC+08:00)', { exact: true }).fill('2027-10-22T00:00');
      await page.getByLabel('Batas penggunaan total', { exact: true }).fill('3');
      await page.getByLabel('Batas penggunaan per akun', { exact: true }).fill('2');
      await page.getByLabel('Alasan perubahan', { exact: true }).fill('Launch voucher for synthetic testing');
      await page.getByRole('button', { name: 'Buat voucher', exact: true }).click();
      await expect(page).toHaveURL(/\/admin\/vouchers\/\d+$/);
      await expect(page.getByRole('heading', { level: 1 })).toHaveText(code);
      const current = page.getByRole('region', { name: 'Ketentuan saat ini' });
      await expect(current).toContainText('Versi 1 · Diaktifkan · Diskon 10%');
      await expect(page.getByLabel('Alasan perubahan', { exact: true })).toHaveValue('');
      fixture({ action: 'usage', email, code });
      await page.reload();
      await expect(page.getByRole('region', { name: 'Penggunaan voucher' })).toContainText('1 reservasi ditahan · 1 penggunaan berhasil · 1 reservasi dilepas');
      await expect(page.getByRole('region', { name: 'Reservasi voucher' })).toContainText('Versi 1');
      await page.getByLabel('Jenis diskon', { exact: true }).selectOption('fixed');
      await page.getByLabel('Nominal diskon (IDR)', { exact: true }).fill('5000');
      await page.getByLabel('Batas penggunaan total', { exact: true }).fill('1');
      await page.getByLabel('Alasan perubahan', { exact: true }).fill('Change to fixed IDR discount');
      await page.getByRole('button', { name: 'Simpan versi baru', exact: true }).click();
      await expect(page.getByRole('alert').first()).toContainText('Batas tidak boleh kurang');
      await expect(page.getByLabel('Nominal diskon (IDR)', { exact: true })).toHaveValue('5000');
      await expect(page.getByLabel('Alasan perubahan', { exact: true })).toHaveValue('Change to fixed IDR discount');
      await page.getByRole('form', { name: 'Pengaturan voucher' }).screenshot({ path: `test-results/voucher-validation-${width}-${theme}.png` });
      await page.getByLabel('Batas penggunaan total', { exact: true }).fill('2');
      await page.getByRole('button', { name: /Aktifkan tema/ }).click();
      await expect(page.getByLabel('Nominal diskon (IDR)', { exact: true })).toHaveValue('5000');
      await page.getByRole('button', { name: /Aktifkan tema/ }).click();
      await page.getByRole('button', { name: 'Simpan versi baru', exact: true }).click();
      await expect(current).toContainText('Versi 2 · Diaktifkan · Diskon Rp5.000');
      await expect(page.getByLabel('Mulai (WITA, UTC+08:00)', { exact: true })).toHaveValue('2026-10-22T00:00');
      fixture({ action: 'edit', email, code });
      await page.getByLabel('Alasan perubahan', { exact: true }).fill('Stale edit must be rejected');
      await page.getByRole('button', { name: 'Simpan versi baru', exact: true }).click();
      await expect(page.getByRole('alert').first()).toContainText('Versi telah berubah');
      await expect(page.getByLabel('Nominal diskon (IDR)', { exact: true })).toHaveValue('5000');
      await page.getByRole('button', { name: 'Muat versi terbaru (buang perubahan)', exact: true }).click();
      await expect(current).toContainText('Versi 3 · Diaktifkan · Diskon Rp6.000');
      await expect(page.getByLabel('Nominal diskon (IDR)', { exact: true })).toHaveValue('6000');
      await page.getByLabel('Aktifkan voucher untuk pesanan baru', { exact: true }).uncheck();
      await page.getByLabel('Alasan perubahan', { exact: true }).fill('Disable new orders');
      await page.getByRole('button', { name: 'Simpan versi baru', exact: true }).click();
      await expect(current).toContainText('Versi 4 · Dinonaktifkan');
      await expect(page.getByLabel('Mulai (WITA, UTC+08:00)', { exact: true })).toHaveValue('2026-10-22T00:00');
      await expect(page.getByRole('region', { name: 'Riwayat versi' })).toContainText('Diskon 10%');
      await expect(page.getByRole('region', { name: 'Audit voucher' })).toContainText('Dinonaktifkan · Versi 4');
      await expect(page.getByRole('region', { name: 'Penukaran voucher' })).toContainText('Versi 1');
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
      await current.screenshot({ path: `test-results/voucher-current-${width}-${theme}.png` });
      await page.getByRole('region', { name: 'Riwayat versi' }).screenshot({ path: `test-results/voucher-history-${width}-${theme}.png` });
      const detailUrl = page.url();
      await page.getByRole('link', { name: 'Semua voucher', exact: true }).click();
      await expect(page.getByRole('article', { name: code, exact: true })).toContainText('Dinonaktifkan');
      await page.getByRole('link', { name: 'Buat voucher', exact: true }).click();
      await page.getByLabel('Kode voucher', { exact: true }).fill(code.toLowerCase());
      await page.getByLabel('Mulai (WITA, UTC+08:00)', { exact: true }).fill('2026-10-22T00:00');
      await page.getByLabel('Berakhir (WITA, UTC+08:00)', { exact: true }).fill('2027-10-22T00:00');
      await page.getByLabel('Alasan perubahan', { exact: true }).fill('Duplicate attempt');
      await page.getByRole('button', { name: 'Buat voucher', exact: true }).click();
      await expect(page.getByLabel('Kode voucher', { exact: true })).toHaveAttribute('aria-invalid', 'true');
      await page.goto(detailUrl);
      await expect(current).toContainText('Versi 4 · Dinonaktifkan');
      expect(errors).toEqual([]);
    });
  }
}
