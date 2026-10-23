import { expect, test } from '@playwright/test';
import { spawnSync } from 'node:child_process';

function fixture(input: object) {
  const result = spawnSync('php', ['tests/Support/project-browser-fixture.php'], {
    input: JSON.stringify(input), encoding: 'utf8', timeout: 30000,
    env: { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'pgsql', DB_DATABASE: 'snappik_test', DB_URL: '', CACHE_STORE: 'array', AUTH_MAILER: 'array' },
  });
  expect(result.status, 'Isolated synthetic project fixture').toBe(0);
}

for (const width of [360, 1440]) {
  for (const theme of ['light', 'dark'] as const) {
    test(`shared selection, uncertain save, review, lock and reopen ${width}px ${theme}`, async ({ page, browser }, info) => {
      test.skip(info.project.name !== 'selection-flow', 'Dedicated selection test-database server only.');
      test.setTimeout(60000);
      const email = `browser-project-selection-${Date.now()}-${width}-${theme}@example.test`;
      fixture({ action: 'seed', email });
      await page.goto('/login');
      await page.getByLabel('Email', { exact: true }).fill(email);
      await page.getByLabel('Kata sandi', { exact: true }).fill('BrowserProjectPassword123');
      await page.getByRole('button', { name: 'Masuk', exact: true }).click();
      await expect(page).toHaveURL(/\/dashboard$/);
      await page.goto('/projects/create');
      await page.getByLabel('Nama proyek / klien').fill('Synthetic selection browser project');
      await page.getByLabel('Batas pilihan klien').fill('2');
      await page.getByLabel('Folder Google Drive · Pemilihan foto').fill('https://drive.google.com/drive/folders/browser-selection');
      await page.getByText('Kata sandi & batas waktu · Pemilihan foto', { exact: true }).click();
      await page.getByLabel('Kata sandi baru · Pemilihan foto').fill('BrowserSelect123');
      await page.getByRole('button', { name: 'Buat proyek dan impor' }).click();
      await expect(page).toHaveURL(/\/projects\/[a-f0-9-]+$/);
      fixture({ action: 'work' });
      await page.getByRole('button', { name: 'Periksa status impor' }).click();
      await page.getByRole('button', { name: 'Terbitkan Pemilihan foto', exact: true }).click();
      await expect(page.getByRole('region', { name: 'Status Pemilihan foto' })).toContainText('Diterbitkan');
      const share = await page.getByLabel('Tautan klien', { exact: true }).inputValue();
      const context = await browser.newContext({ baseURL: 'http://127.0.0.1:18765', viewport: { width, height: 1000 },
        colorScheme: theme, reducedMotion: width === 360 ? 'reduce' : 'no-preference' });
      // UI-only image fixture. Backend preview scoping/bytes/failure has separate tests.
      await context.route('**/selection/preview/*', route => route.fulfill({ contentType: 'image/png',
        body: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', 'base64') }));
      const first = await context.newPage();
      const errors: string[] = [];
      first.on('pageerror', error => errors.push(error.message));
      await first.goto(share);
      await first.getByLabel('Kata sandi · Pemilihan foto', { exact: true }).fill('BrowserSelect123');
      await first.getByRole('button', { name: 'Buka Pemilihan foto' }).click();
      await first.getByRole('link', { name: 'Buka pemilihan foto' }).click();
      await expect(first.getByRole('heading', { name: '0 / 2 foto dipilih' })).toBeVisible();
      await first.getByRole('button', { name: 'Tinjau pilihan', exact: true }).focus();
      await first.keyboard.press('Enter');
      await expect(first.getByRole('button', { name: 'Konfirmasi pilihan', exact: true })).toBeDisabled();
      await first.getByRole('button', { name: 'Semua foto', exact: true }).click();
      await first.getByRole('button', { name: 'browser-nested', exact: true }).click();
      await expect(first.locator('.photo-tile')).toHaveCount(2);
      const zoom = first.getByRole('button', { name: /Perbesar/ }).first();
      await zoom.click();
      await expect(first.getByRole('dialog')).toBeVisible();
      await first.getByRole('dialog').getByRole('button', { name: 'Berikutnya', exact: true }).click();
      await first.keyboard.press('Escape');
      await expect(zoom).toBeFocused();

      // Server commits, browser loses acknowledgement. Explicit retry must reuse the operation.
      let operationId = '';
      await first.route('**/selection/choices', async route => {
        operationId = route.request().postDataJSON().operation_id;
        await route.fetch();
        await route.abort('failed');
      }, { times: 1 });
      await first.getByRole('button', { name: /^Pilih / }).first().click();
      await expect(first.getByText('Pilihan terakhir belum bisa dipastikan tersimpan.', { exact: false })).toBeVisible();
      const retryRequest = first.waitForRequest(request => request.url().endsWith('/selection/choices'));
      await first.getByRole('button', { name: 'Coba simpan lagi' }).click();
      expect((await retryRequest).postDataJSON().operation_id).toBe(operationId);
      await expect(first.getByText('Pilihan tersimpan.', { exact: true })).toBeVisible();
      await expect(first.getByRole('heading', { name: '1 / 2 foto dipilih' })).toBeVisible();
      await first.reload();
      await expect(first.getByRole('heading', { name: '1 / 2 foto dipilih' })).toBeVisible();
      await first.getByRole('button', { name: 'Tinjau pilihan', exact: true }).click();
      await expect(first.locator('.photo-tile')).toHaveCount(1);

      const second = await context.newPage();
      await second.goto(first.url());
      await expect(second.getByRole('heading', { name: '1 / 2 foto dipilih' })).toBeVisible();
      // Delay a real save and switch theme while the canonical acknowledgement is pending.
      let release!: () => void;
      const barrier = new Promise<void>(resolve => { release = resolve; });
      await second.route('**/selection/choices', async route => { await barrier; await route.continue(); }, { times: 1 });
      await second.getByRole('button', { name: /^Pilih / }).click();
      await expect(second.getByText('Menyimpan…', { exact: true })).toBeVisible();
      await second.getByRole('button', { name: /Aktifkan tema/ }).click();
      release();
      await expect(second.getByRole('heading', { name: '2 / 2 foto dipilih' })).toBeVisible();
      await first.getByRole('button', { name: 'Konfirmasi pilihan', exact: true }).click();
      await first.getByRole('button', { name: 'Ya, konfirmasi dan kunci' }).focus();
      await first.keyboard.press('Enter');
      await expect(first.getByText('Pilihan atau katalog berubah di sesi lain.', { exact: false })).toBeVisible();
      await first.getByRole('button', { name: 'Muat ulang pilihan' }).click();
      await expect(first.locator('.photo-tile')).toHaveCount(2);
      // Confirm below allowance, while the other client still holds an editable stale page.
      await first.getByRole('button', { name: /^Hapus pilihan / }).last().click();
      await expect(first.getByRole('heading', { name: '1 / 2 foto dipilih' })).toBeVisible();
      await first.getByRole('button', { name: 'Konfirmasi pilihan', exact: true }).click();
      await first.getByRole('button', { name: 'Batal', exact: true }).click();
      await expect(first.getByRole('dialog')).not.toBeVisible();
      await first.getByRole('button', { name: 'Konfirmasi pilihan', exact: true }).click();
      await first.getByRole('button', { name: 'Ya, konfirmasi dan kunci' }).click();
      await expect(first.getByText('Konfirmasi tersimpan. Pilihan dikunci.', { exact: true })).toBeVisible();
      await second.getByRole('button', { name: /^Hapus pilihan / }).first().click();
      await expect(second.getByText('Pilihan sudah dikonfirmasi dan dikunci.', { exact: true })).toBeVisible();
      expect(await first.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
      await first.screenshot({ path: `test-results/selection-${width}-${theme}.png`, fullPage: true });
      await page.reload();
      await page.getByRole('button', { name: 'Buka kembali pemilihan', exact: true }).click();
      await page.getByRole('button', { name: 'Konfirmasi buka kembali', exact: true }).click();
      await expect(page.getByRole('button', { name: 'Buka kembali pemilihan', exact: true })).toHaveCount(0);
      await first.getByRole('button', { name: 'Muat ulang pilihan' }).click();
      await expect(first.getByRole('button', { name: 'Konfirmasi pilihan', exact: true })).toBeEnabled();
      await expect(first.getByRole('heading', { name: '1 / 2 foto dipilih' })).toBeVisible();
      if (width === 1440 && theme === 'light') {
        fixture({ action: 'grow-selection', email });
        await first.getByRole('button', { name: 'Semua foto', exact: true }).click();
        await expect(first.locator('.photo-tile')).toHaveCount(36);
        expect(await first.locator('.photo-tile').evaluateAll(tiles => tiles.filter(tile => tile.getAnimations().length > 0).length)).toBeLessThanOrEqual(6);
        await expect(first.getByRole('navigation', { name: 'Halaman foto' })).toContainText('2001 foto');
        await first.getByRole('button', { name: 'Foto berikutnya', exact: true }).click();
        await expect(first.getByRole('navigation', { name: 'Halaman foto' })).toContainText('Halaman 2 / 56');
        await expect(first.locator('.photo-tile')).toHaveCount(36);
        await first.getByRole('button', { name: /^Pilih / }).first().click();
        await expect(first.getByRole('heading', { name: '2 / 2 foto dipilih' })).toBeVisible();
        await first.getByRole('button', { name: 'Foto sebelumnya', exact: true }).click();
        await expect(first.getByRole('navigation', { name: 'Halaman foto' })).toContainText('Halaman 1 / 56');
        await expect(first.getByRole('button', { name: /^Hapus pilihan / })).toHaveCount(1);
        await expect(first.getByRole('button', { name: /^Pilih / }).first()).toBeEnabled();
        fixture({ action: 'expire-selection-account', email });
        await first.getByRole('button', { name: /^Pilih / }).first().click();
        await expect(first.getByText('Masa akses fotografer berakhir. Pilihan tersimpan tetap tersedia.', { exact: true })).toBeVisible();
        await first.getByRole('button', { name: 'Muat ulang pilihan' }).click();
        await expect(first.getByText('Masa akses fotografer berakhir. Pilihan tersimpan hanya dapat dibaca.', { exact: true })).toBeVisible();
        await expect(first.getByRole('heading', { name: '2 / 2 foto dipilih' })).toBeVisible();
        fixture({ action: 'close-selection-deadline', email });
        await first.getByRole('button', { name: 'Muat ulang pilihan' }).click();
        await expect(first.getByText('Galeri gagal dimuat.', { exact: false })).toBeVisible();
      }
      expect(errors).toEqual([]);
      await context.close();
    });
  }
}
