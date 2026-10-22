import { expect, test } from '@playwright/test';
import { spawnSync } from 'node:child_process';

function fixture(input: object) {
  const result = spawnSync('php', ['tests/Support/project-browser-fixture.php'], {
    input: JSON.stringify(input), encoding: 'utf8', timeout: 30000,
    env: { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'pgsql', DB_DATABASE: 'snappik_test', DB_URL: '', CACHE_STORE: 'array', AUTH_MAILER: 'array' },
  });
  expect(result.status, 'CLI fixture succeeds without external services').toBe(0);
}

for (const width of [360, 1440]) {
  for (const theme of ['light', 'dark'] as const) {
    test(`project configuration, worker, publication and archive ${width}px ${theme}`, async ({ page, context }, info) => {
      test.skip(info.project.name !== 'projects-flow', 'Writes only to the dedicated project test database server.');
      const email = `browser-project-${Date.now()}-${width}-${theme}@example.test`;
      fixture({ action: 'seed', email });
      await page.setViewportSize({ width, height: 1000 });
      await page.emulateMedia({ colorScheme: theme, reducedMotion: width === 360 ? 'reduce' : 'no-preference' });
      const errors: string[] = [];
      page.on('pageerror', error => errors.push(error.message));
      await page.goto('/login');
      await page.getByLabel('Email', { exact: true }).fill(email);
      await page.getByLabel('Kata sandi', { exact: true }).fill('BrowserProjectPassword123');
      await page.getByRole('button', { name: 'Masuk', exact: true }).click();
      await expect(page).toHaveURL(/\/dashboard$/);
      await page.getByRole('link', { name: 'Kelola proyek' }).click();
      await page.getByRole('link', { name: 'Buat proyek', exact: true }).click();
      await expect(page.getByRole('heading', { name: 'Buat proyek', exact: true })).toBeVisible();
      await page.getByLabel('Nama proyek / klien').fill('Synthetic browser project');
      await page.getByLabel('Folder Google Drive · Pemilihan foto').fill('https://drive.google.com/drive/folders/browser-selection');
      await page.getByLabel('Folder Google Drive · Hasil akhir').fill('https://drive.google.com/drive/folders/browser-delivery');
      await page.getByLabel('Kata sandi baru · Pemilihan foto').fill('BrowserSelect123');
      await page.getByLabel('Kata sandi baru · Hasil akhir').fill('BrowserDelivery123');
      await page.getByRole('button', { name: /Aktifkan tema/ }).click();
      await expect(page.getByLabel('Nama proyek / klien')).toHaveValue('Synthetic browser project');
      await page.getByRole('button', { name: /Aktifkan tema/ }).click();
      await page.getByRole('button', { name: 'Buat proyek dan impor' }).click();
      await expect(page).toHaveURL(/\/projects\/[a-f0-9-]+$/);
      fixture({ action: 'work' });
      await page.getByRole('button', { name: 'Periksa status impor' }).click();
      const selection = page.getByRole('region', { name: 'Status Pemilihan foto' });
      await expect(selection).toContainText('Impor selesai');
      await expect(selection).toContainText('2 foto');
      await expect(selection).toContainText('camera.raw');
      await selection.getByRole('link', { name: 'Lihat semua berkas yang dikecualikan' }).click();
      await expect(page.getByRole('heading', { name: 'Berkas tidak didukung dan pintasan' })).toBeVisible();
      await expect(page.getByRole('listitem')).toContainText('camera.raw');
      await page.getByRole('link', { name: 'Kembali ke proyek' }).click();
      await expect(page.getByRole('button', { name: 'Simpan pengaturan' })).toBeVisible();
      await expect(page.getByText('Perubahan belum disimpan.')).toHaveCount(0);
      await page.getByLabel('Nama proyek / klien').fill('Updated browser project');
      await page.getByRole('button', { name: 'Simpan pengaturan' }).click();
      await expect(page.getByRole('heading', { level: 1 })).toHaveText('Updated browser project');
      await expect(page.getByText('Perubahan belum disimpan.')).toHaveCount(0);
      await page.getByRole('button', { name: 'Terbitkan Pemilihan foto', exact: true }).click();
      await expect(selection).toContainText('Diterbitkan');
      await page.getByRole('button', { name: 'Terbitkan Hasil akhir', exact: true }).click();
      const share = await page.getByLabel('Tautan klien', { exact: true }).inputValue();
      const client = await context.newPage();
      await client.goto(share);
      await client.getByLabel('Kata sandi · Pemilihan foto', { exact: true }).fill('BrowserSelect123');
      await client.getByRole('button', { name: 'Buka Pemilihan foto' }).click();
      await expect(client.getByText('Galeri diterbitkan · 2 foto.', { exact: false })).toBeVisible();
      await expect(client.getByLabel('Kata sandi · Hasil akhir', { exact: true })).toBeVisible();
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
      // Omit capabilities and private source links from review screenshots.
      await page.screenshot({ path: `test-results/projects-${width}-${theme}.png`, fullPage: true,
        mask: [page.getByLabel('Tautan klien', { exact: true }), page.getByLabel(/Folder Google Drive/)] });
      await page.getByRole('button', { name: 'Ganti tautan klien', exact: true }).click();
      await page.getByRole('button', { name: 'Konfirmasi ganti tautan' }).click();
      await expect.poll(async () => page.getByLabel('Tautan klien', { exact: true }).inputValue()).not.toBe(share);
      expect((await client.goto(share))?.status()).toBe(404);
      const newShare = await page.getByLabel('Tautan klien', { exact: true }).inputValue();
      await page.getByRole('button', { name: 'Arsipkan proyek', exact: true }).click();
      await expect(page.getByText('Proyek diarsipkan. Semua tautan klien ditutup.')).toBeVisible();
      expect((await client.goto(newShare))?.status()).toBe(404);
      await page.getByRole('button', { name: 'Pulihkan proyek', exact: true }).click();
      await expect(page.getByRole('button', { name: 'Arsipkan proyek', exact: true })).toBeVisible();
      expect((await client.goto(newShare))?.status()).toBe(200);
      expect(errors).toEqual([]);
      await client.close();
    });
  }
}
