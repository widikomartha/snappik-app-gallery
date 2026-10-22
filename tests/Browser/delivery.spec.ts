import { expect, test } from '@playwright/test';
import { spawnSync } from 'node:child_process';
import { readFile } from 'node:fs/promises';

function fixture(input: object) {
  const result = spawnSync('php', ['tests/Support/delivery-browser-fixture.php'], {
    input: JSON.stringify(input), encoding: 'utf8', timeout: 30000,
    env: { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'pgsql', DB_DATABASE: 'snappik_test', DB_URL: '', CACHE_STORE: 'array', AUTH_MAILER: 'array' },
  });
  expect(result.status, `Isolated synthetic delivery fixture: ${result.stderr}`).toBe(0);
}

for (const width of [360, 1440]) {
  for (const theme of ['light', 'dark'] as const) {
    test(`finished gallery and actual browser attachment ${width}px ${theme}`, async ({ page, browser }, info) => {
      test.skip(info.project.name !== 'delivery-flow', 'Dedicated delivery test-database server only.');
      const email = `browser-delivery-${Date.now()}-${width}-${theme}@example.test`;
      fixture({ action: 'seed', email });
      await page.goto('/login');
      await page.getByLabel('Email', { exact: true }).fill(email);
      await page.getByLabel('Kata sandi', { exact: true }).fill('BrowserDeliveryPassword123');
      await page.getByRole('button', { name: 'Masuk', exact: true }).click();
      await expect(page).toHaveURL(/\/dashboard$/);
      await page.getByRole('link', { name: 'Kelola proyek', exact: true }).click();
      await page.getByRole('link', { name: 'Synthetic finished gallery', exact: true }).click();
      const share = await page.getByLabel('Tautan klien', { exact: true }).inputValue();
      const context = await browser.newContext({ baseURL: 'http://127.0.0.1:18767', viewport: { width, height: 1000 }, colorScheme: theme, reducedMotion: width === 360 ? 'reduce' : 'no-preference' });
      const client = await context.newPage();
      const errors: string[] = [];
      client.on('pageerror', error => errors.push(error.message));
      await client.goto(share);
      await client.getByLabel('Kata sandi · Pemilihan foto', { exact: true }).fill('Selection123');
      await client.getByRole('button', { name: 'Buka Pemilihan foto', exact: true }).click();
      await expect(client.getByRole('link', { name: 'Buka hasil akhir', exact: true })).toHaveCount(0);
      await client.getByLabel('Kata sandi · Hasil akhir', { exact: true }).fill('Delivery123');
      await client.getByRole('button', { name: 'Buka Hasil akhir', exact: true }).click();
      const thumbnailResponse = client.waitForResponse(response => response.url().includes('/delivery/media/') && response.url().includes('thumbnail=1'));
      await client.getByRole('link', { name: 'Buka hasil akhir', exact: true }).click();
      expect((await thumbnailResponse).status(), 'Thumbnail proxy HTTP response').toBe(200);
      await expect(client.locator('.photo-tile')).toHaveCount(2);
      await expect(client.locator('.photo-tile img').first()).toBeVisible();
      await client.getByRole('button', { name: 'delivery-nested', exact: true }).click();
      const zoom = client.getByRole('button', { name: /Perbesar/ }).first();
      await zoom.click();
      await expect(client.getByRole('dialog')).toBeVisible();
      await client.getByRole('dialog').getByRole('button', { name: 'Berikutnya', exact: true }).click();
      await client.keyboard.press('Escape');
      await expect(zoom).toBeFocused();
      fixture({ action: 'expire-account', email });
      await client.reload();
      await expect(client.locator('.photo-tile')).toHaveCount(2);
      const mediaUrl = (await client.getByRole('link', { name: /^Unduh asli / }).first().getAttribute('href'))!;
      const downloadEvent = client.waitForEvent('download');
      await client.getByRole('link', { name: /^Unduh asli / }).first().click();
      const download = await downloadEvent;
      expect(download.suggestedFilename()).toBe('Hasil akhir.png');
      expect(await download.failure()).toBeNull();
      const bytes = await readFile((await download.path())!);
      expect(bytes.equals(Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', 'base64'))).toBe(true);
      const partial = await context.request.get(mediaUrl, { headers: { Range: 'bytes=4-15' } });
      expect(partial.status()).toBe(206);
      expect((await partial.body()).equals(bytes.subarray(4, 16))).toBe(true);
      expect(await client.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
      await client.screenshot({ path: `test-results/delivery-${width}-${theme}.png`, fullPage: true });
      fixture({ action: 'revoke-download', email });
      const popupEvent = client.waitForEvent('popup');
      await client.getByRole('link', { name: /^Unduh asli / }).first().click();
      const popup = await popupEvent;
      await expect(popup.locator('body')).toContainText('izin unduh dicabut');
      await popup.close();
      await zoom.click();
      await expect(client.getByRole('dialog').getByText('Pratinjau tidak tersedia.', { exact: false })).toBeVisible();
      await client.keyboard.press('Escape');
      fixture({ action: 'close-delivery', email });
      expect((await context.request.get(mediaUrl, { headers: { Range: 'bytes=4-15' } })).status()).toBe(404);
      await client.getByRole('button', { name: 'Periksa hasil akhir terbaru', exact: true }).click();
      await expect(client.getByText('Galeri gagal dimuat.', { exact: false })).toBeVisible();
      await expect(client.getByRole('link', { name: /^Unduh asli / })).toHaveCount(0);
      expect(errors).toEqual([]);
      await context.close();
    });
  }
}
