import { expect, test } from '@playwright/test';
import { spawnSync } from 'node:child_process';

function fixture(input: object) {
  const result = spawnSync('php', ['tests/Support/progress-browser-fixture.php'], {
    input: JSON.stringify(input), encoding: 'utf8', timeout: 30000,
    env: { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'pgsql', DB_DATABASE: 'snappik_test', DB_URL: '', CACHE_STORE: 'array', AUTH_MAILER: 'array' },
  });
  expect(result.status, 'Isolated synthetic progress fixture').toBe(0);
}

for (const width of [360, 1440]) {
  for (const theme of ['light', 'dark'] as const) {
    test(`progress, history, stale reads and clipboard fallback ${width}px ${theme}`, async ({ page }, info) => {
      test.skip(info.project.name !== 'progress-flow', 'Dedicated progress test-database server only.');
      test.setTimeout(60000);
      await page.setViewportSize({ width, height: 1000 });
      await page.emulateMedia({ colorScheme: theme, reducedMotion: width === 360 ? 'reduce' : 'no-preference' });
      const errors: string[] = [];
      page.on('pageerror', error => errors.push(error.message));
      const email = `browser-progress-${Date.now()}-${width}-${theme}@example.test`;
      fixture({ action: 'seed', email });
      await page.goto('/login');
      await page.getByLabel('Email', { exact: true }).fill(email);
      await page.getByLabel('Kata sandi', { exact: true }).fill('BrowserProgressPassword123');
      await page.getByRole('button', { name: 'Masuk', exact: true }).click();
      await expect(page).toHaveURL(/\/dashboard$/);
      await expect(page.getByRole('region', { name: 'Progres proyek' })).toContainText('Sedang memilih · 2 / 3 foto dipilih');
      await page.screenshot({ path: `test-results/progress-overview-${width}-${theme}.png`, fullPage: true });
      await page.getByRole('link', { name: 'Lihat hasil dan salin nama berkas', exact: true }).click();
      await expect(page.getByRole('region', { name: 'Hasil tersimpan' })).toContainText('Pilihan draf yang sudah tersimpan');
      const resultUrl = page.url();
      await page.getByRole('button', { name: 'Siapkan daftar nama berkas' }).click();
      const output = page.getByLabel('Daftar nama berkas lengkap');
      await expect(output).toHaveValue('nested/same.jpg [ID: one]\nnested/same.jpg [ID: two]\n');
      // Clipboard API explicitly denies; selectable output remains available.
      await page.evaluate(() => Object.defineProperty(navigator, 'clipboard', { configurable: true, value: {
        writeText: async () => { throw new DOMException('Denied', 'NotAllowedError'); },
      } }));
      await page.getByRole('button', { name: 'Salin nama berkas', exact: true }).click();
      await expect(page.getByText('Papan klip ditolak.', { exact: false })).toBeVisible();
      await expect(output).toBeFocused();
      expect(await output.evaluate(element => (element as HTMLTextAreaElement).selectionEnd - (element as HTMLTextAreaElement).selectionStart)).toBeGreaterThan(0);
      // A successful copy reports acknowledgement and preserves the same text.
      await page.evaluate(() => Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText: async () => undefined } }));
      await page.getByRole('button', { name: 'Salin nama berkas', exact: true }).click();
      await expect(page.getByText('Nama berkas disalin.', { exact: true })).toBeVisible();

      await page.route('**/results/data*', route => route.fulfill({ status: 503, contentType: 'application/json', body: '{}' }), { times: 1 });
      await page.getByRole('button', { name: 'Periksa hasil terbaru' }).click();
      await expect(page.getByText('Penyegaran gagal.', { exact: false })).toBeVisible();
      await expect(page.getByRole('region', { name: 'Progres saat ini' })).toContainText('2 / 3 foto dipilih');
      await expect(output).toHaveValue('nested/same.jpg [ID: one]\nnested/same.jpg [ID: two]\n');
      fixture({ action: 'confirm', email });
      await page.getByRole('button', { name: 'Periksa hasil terbaru' }).click();
      await expect(page.getByRole('region', { name: 'Progres saat ini' })).toContainText('Dikonfirmasi');
      fixture({ action: 'reopen', email });
      await page.getByRole('button', { name: 'Periksa hasil terbaru' }).click();
      await expect(page.getByRole('region', { name: 'Progres saat ini' })).toContainText('Sedang memilih');
      await expect(page.getByRole('region', { name: 'Riwayat konfirmasi' })).toContainText('Riwayat, bukan konfirmasi saat ini');
      fixture({ action: 'remove-original', email });
      await page.goto('/dashboard');
      await expect(page.getByRole('region', { name: 'Progres proyek' })).toContainText('Uji coba aktif.');
      fixture({ action: 'expire', email });
      fixture({ action: 'failed-refresh', email });
      await page.getByRole('button', { name: 'Periksa progres' }).click();
      await expect(page.getByRole('region', { name: 'Progres proyek' })).toContainText('Akses berakhir. Metadata');
      await expect(page.getByRole('region', { name: 'Progres proyek' })).not.toContainText('Uji coba aktif.');
      await page.getByRole('link', { name: 'Lihat hasil dan salin nama berkas', exact: true }).click();
      await page.getByRole('link', { name: 'Konfirmasi putaran 1', exact: true }).click();
      await expect(page.getByRole('region', { name: 'Progres saat ini' })).toContainText('Dibekukan · akses akun berakhir');
      await expect(page.getByRole('region', { name: 'Progres saat ini' })).toContainText('Penyegaran katalog gagal');
      await expect(page.getByRole('region', { name: 'Hasil tersimpan' })).toContainText('Tidak tersedia dalam katalog terbit saat ini');
      await expect(page.locator('.result-records li')).toHaveCount(2);
      await page.getByRole('button', { name: 'Siapkan daftar nama berkas' }).click();
      await expect(page.getByLabel('Daftar nama berkas lengkap')).toHaveValue('nested/same.jpg [ID: one]\nnested/same.jpg [ID: two]\n');
      const before = await page.getByLabel('Daftar nama berkas lengkap').inputValue();
      await page.getByRole('button', { name: /Aktifkan tema/ }).click();
      await expect(page.getByLabel('Daftar nama berkas lengkap')).toHaveValue(before);
      await page.getByRole('button', { name: /Aktifkan tema/ }).click();
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
      await page.screenshot({ path: `test-results/progress-history-${width}-${theme}.png`, fullPage: true });
      await page.goto(resultUrl);
      await expect(page.locator('.result-records li')).toHaveCount(1);
      await page.getByRole('button', { name: 'Siapkan daftar nama berkas' }).click();
      await expect(page.getByLabel('Daftar nama berkas lengkap')).toHaveValue('same.jpg\n');
      expect(errors).toEqual([]);
    });
  }
}
