import { expect, test } from '@playwright/test';
import { spawnSync } from 'node:child_process';

function fixture(input: object): { owner: number; order: string; import: string; archive: string } {
  const result = spawnSync('php', ['tests/Support/operations-browser-fixture.php'], {
    input: JSON.stringify(input), encoding: 'utf8', timeout: 30000,
    env: { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'pgsql', DB_DATABASE: 'snappik_test', DB_URL: '', CACHE_STORE: 'array', AUTH_MAILER: 'array' },
  });
  expect(result.status, `Synthetic operations fixture: ${result.stderr}`).toBe(0);
  return JSON.parse(result.stdout);
}

for (const width of [360, 1440]) {
  for (const theme of ['light', 'dark'] as const) {
    test(`owner investigates failures, versions price and suspends account ${width}px ${theme}`, async ({ page }, info) => {
      test.skip(info.project.name !== 'operations-flow', 'Dedicated test database server only.');
      test.setTimeout(90000);
      await page.setViewportSize({ width, height: 1000 });
      await page.emulateMedia({ colorScheme: theme, reducedMotion: width === 360 ? 'reduce' : 'no-preference' });
      const errors: string[] = [];
      page.on('pageerror', e => errors.push(e.message));
      const suffix = `${Date.now()}-${width}-${theme}`;
      const email = `browser-operations-${suffix}@example.test`;
      const name = `Operasi ${suffix}`;
      const data = fixture({ action: 'seed', email, name });
      await page.goto('/login');
      await page.getByLabel('Email', { exact: true }).fill(email);
      await page.getByLabel('Kata sandi', { exact: true }).fill('BrowserOperationsPassword123');
      await page.getByRole('button', { name: 'Masuk', exact: true }).click();
      await expect(page).toHaveURL(/\/admin$/);
      await expect(page.getByRole('region', { name: 'Ringkasan pembayaran' })).toContainText('Penerimaan terverifikasi');
      await page.screenshot({ path: `test-results/operations-overview-${width}-${theme}.png`, fullPage: true });
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);

      await page.getByRole('link', { name: 'Harga Pro', exact: true }).click();
      await page.getByLabel('Harga baru (IDR)').fill('49000');
      await page.getByLabel('Alasan perubahan', { exact: true }).fill('Future price for synthetic checkout');
      await page.getByRole('button', { name: /Aktifkan tema/ }).click();
      await expect(page.getByLabel('Harga baru (IDR)')).toHaveValue('49000');
      await page.getByRole('button', { name: /Aktifkan tema/ }).click();
      fixture({ action: 'price-race', email });
      await page.getByRole('button', { name: 'Simpan versi harga', exact: true }).click();
      await expect(page.getByRole('alert')).toContainText('Versi atau akses telah berubah');
      await expect(page.getByLabel('Harga baru (IDR)')).toHaveValue('49000');
      await page.getByRole('button', { name: 'Muat ulang (buang perubahan)', exact: true }).click();
      await expect(page.getByLabel('Harga baru (IDR)')).toHaveValue('39000');
      await page.getByLabel('Harga baru (IDR)').fill('49000');
      await page.getByLabel('Alasan perubahan', { exact: true }).fill('Publish future synthetic price');
      await page.getByRole('button', { name: 'Simpan versi harga', exact: true }).click();
      await expect(page.getByRole('region', { name: 'Riwayat harga' })).toContainText('Rp49.000');
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);

      await page.goto(`/admin/accounts/${data.owner}`);
      const account = page.getByRole('region', { name: 'Status akun' });
      fixture({ action: 'suspend-race', email });
      await account.getByLabel('Alasan: Tangguhkan akun').fill('Synthetic suspension investigation');
      await account.getByRole('button', { name: 'Tangguhkan akun', exact: true }).click();
      await expect(page.getByRole('alert')).toContainText('Versi telah berubah');
      await expect(account.getByLabel('Alasan: Tangguhkan akun')).toHaveValue('Synthetic suspension investigation');
      await account.getByRole('button', { name: 'Muat ulang (buang perubahan)', exact: true }).click();
      await expect(page.getByRole('button', { name: 'Aktifkan kembali akun', exact: true })).toBeVisible();
      await account.getByLabel('Alasan: Aktifkan kembali akun').fill('Synthetic investigation complete');
      await account.getByRole('button', { name: 'Aktifkan kembali akun', exact: true }).click();
      await expect(page.getByRole('button', { name: 'Tangguhkan akun', exact: true })).toBeVisible();
      await expect(page.getByRole('region', { name: 'Metadata proyek' })).toContainText(name);
      await page.screenshot({ path: `test-results/operations-account-${width}-${theme}.png`, fullPage: true });
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);

      await page.getByRole('link', { name: 'Pekerjaan', exact: true }).click();
      const failedImport = page.getByRole('article', { name: `Impor katalog ${name}`, exact: true });
      const failedZip = page.getByRole('article', { name: `ZIP ${name}`, exact: true });
      await expect(failedImport).toContainText('Pekerja terhenti');
      await page.screenshot({ path: `test-results/operations-failures-${width}-${theme}.png`, fullPage: true });
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
      await failedImport.getByLabel('Alasan: Coba ulang Impor katalog').fill('Retry interrupted synthetic import');
      await failedImport.getByRole('button', { name: 'Coba ulang Impor katalog', exact: true }).click();
      await expect(failedImport).toContainText('Dalam antrean');
      fixture({ action: 'work-import', email });
      await failedZip.getByLabel('Alasan: Coba ulang ZIP').fill('Retry interrupted synthetic ZIP');
      await failedZip.getByRole('button', { name: 'Coba ulang ZIP', exact: true }).click();
      await expect(failedZip).toContainText('Dalam antrean');
      fixture({ action: 'work-archive', email });
      await page.reload();
      await expect(failedImport).toHaveCount(0);
      await expect(failedZip).toHaveCount(0);
      await page.screenshot({ path: `test-results/operations-jobs-${width}-${theme}.png`, fullPage: true });

      await page.goto(`/admin/orders/${data.order}`);
      await expect(page.getByRole('region', { name: 'Kontrak pesanan' })).toContainText('Perlu tinjauan penyedia');
      await page.getByLabel('Alasan: Antrikan pemeriksaan penyedia').fill('Investigate synthetic verification mismatch');
      await page.getByRole('button', { name: 'Antrikan pemeriksaan penyedia', exact: true }).click();
      await expect(page.getByRole('region', { name: 'Riwayat pembayaran' })).toContainText('admin');
      fixture({ action: 'work-payment', email });
      await page.reload();
      await expect(page.getByRole('region', { name: 'Riwayat pembayaran' })).toContainText('Selesai');
      await expect(page.getByRole('region', { name: 'Kontrak pesanan' })).toContainText('Perlu tinjauan penyedia');
      await page.screenshot({ path: `test-results/operations-review-${width}-${theme}.png`, fullPage: true });
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
      expect(errors).toEqual([]);
    });
  }
}
