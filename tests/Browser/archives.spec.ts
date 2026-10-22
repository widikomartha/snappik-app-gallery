import { expect, test } from '@playwright/test';
import { spawn, spawnSync } from 'node:child_process';
import { readFile } from 'node:fs/promises';

function fixture(input: object) {
  const result = spawnSync('php', ['tests/Support/delivery-browser-fixture.php'], {
    input: JSON.stringify(input), encoding: 'utf8', timeout: 30000,
    env: { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'pgsql', DB_DATABASE: 'snappik_test', DB_URL: '', CACHE_STORE: 'array', AUTH_MAILER: 'array' },
  });
  expect(result.status, `Isolated archive fixture: ${result.stderr}`).toBe(0);
}

for (const width of [360, 1440]) for (const theme of ['light', 'dark'] as const) {
  test(`persistent ZIP, download and recreation ${width}px ${theme}`, async ({ page, browser }, info) => {
    test.skip(info.project.name !== 'archive-flow', 'Dedicated archive test-database server only.');
    const email = `browser-delivery-zip-${Date.now()}-${width}-${theme}@example.test`;
    fixture({ action: 'seed', email });
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill(email);
    await page.getByLabel('Kata sandi', { exact: true }).fill('BrowserDeliveryPassword123');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.getByRole('link', { name: 'Kelola proyek', exact: true }).click();
    await page.getByRole('link', { name: 'Synthetic finished gallery', exact: true }).click();
    const share = await page.getByLabel('Tautan klien', { exact: true }).inputValue();
    const context = await browser.newContext({ baseURL: 'http://127.0.0.1:18768', viewport: { width, height: 1000 }, colorScheme: theme, reducedMotion: width === 360 ? 'reduce' : 'no-preference' });
    let client = await context.newPage();
    await client.goto(share);
    await client.getByLabel('Kata sandi · Hasil akhir', { exact: true }).fill('Delivery123');
    await client.getByRole('button', { name: 'Buka Hasil akhir', exact: true }).click();
    await client.getByRole('link', { name: 'Buka hasil akhir', exact: true }).click();
    await expect(client).toHaveURL(/\/delivery$/);
    const deliveryUrl = client.url();
    fixture({ action: 'expire-account', email });
    await client.getByRole('button', { name: 'Siapkan ZIP semua foto', exact: true }).click();
    await expect(client.getByText('ZIP menunggu antrean.', { exact: true })).toBeVisible();
    await client.close();
    client = await context.newPage();
    await client.goto(deliveryUrl);
    await expect(client.getByText('ZIP menunggu antrean.', { exact: true })).toBeVisible();
    const themeBefore = await client.locator('html').getAttribute('data-theme');
    await client.getByRole('button', { name: /Aktifkan tema/ }).click();
    await expect(client.locator('html')).not.toHaveAttribute('data-theme', themeBefore!);
    await expect(client.getByText('ZIP menunggu antrean.', { exact: true })).toBeVisible();
    await client.getByRole('button', { name: /Aktifkan tema/ }).click();
    const current = await (await context.request.get(`${deliveryUrl}/archive`)).json();
    const child = spawn('php', ['tests/Support/archive-process-worker.php'], { env: { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'pgsql', DB_DATABASE: 'snappik_test', DB_URL: '', CACHE_STORE: 'array', SESSION_DRIVER: 'array' }, stdio: ['pipe', 'pipe', 'pipe'] });
    const exited = new Promise<void>(resolve => child.once('exit', () => resolve()));
    child.stdin.end(JSON.stringify({ action: 'hang', archive_id: current.archive.id }));
    try {
      await expect.poll(async () => (await (await context.request.get(`${deliveryUrl}/archive`)).json()).archive.state).toBe('processing');
      await client.getByRole('button', { name: 'Periksa status ZIP', exact: true }).click();
      await expect(client.getByText('Menyiapkan ZIP · 0 / 2 foto selesai.', { exact: true })).toBeVisible();
      await client.screenshot({ path: `test-results/archive-processing-${width}-${theme}.png`, fullPage: true });
    } finally { child.kill(); await exited; }
    fixture({ action: 'recover-archive', email });
    await client.getByRole('button', { name: 'Periksa status ZIP', exact: true }).click();
    await expect(client.getByText('ZIP gagal.', { exact: false })).toBeVisible();
    await client.screenshot({ path: `test-results/archive-failed-${width}-${theme}.png`, fullPage: true });
    await client.getByRole('button', { name: 'Buat ulang ZIP', exact: true }).click();
    await expect(client.getByText('ZIP menunggu antrean.', { exact: true })).toBeVisible();
    fixture({ action: 'work-archive', email });
    await client.getByRole('button', { name: 'Periksa status ZIP', exact: true }).click();
    await expect(client.getByText('ZIP lengkap siap', { exact: false })).toBeVisible();
    const url = (await client.getByRole('link', { name: 'Unduh ZIP lengkap', exact: true }).getAttribute('href'))!;
    const downloadEvent = client.waitForEvent('download');
    await client.getByRole('link', { name: 'Unduh ZIP lengkap', exact: true }).click();
    const download = await downloadEvent;
    expect(await download.failure()).toBeNull();
    expect(download.suggestedFilename()).toBe('SnapPik-hasil-akhir.zip');
    const bytes = await readFile((await download.path())!);
    expect(bytes.subarray(0, 4).toString('hex')).toBe('504b0304');
    expect(bytes.readUInt16LE(bytes.length - 12)).toBe(2); // complete ZIP directory entry count
    const partial = await context.request.get(url, { headers: { Range: 'bytes=4-20' } });
    expect(partial.status()).toBe(206);
    expect((await partial.body()).equals(bytes.subarray(4, 21))).toBe(true);
    await client.route('**/delivery/archive', route => route.fulfill({ status: 503 }));
    await client.getByRole('button', { name: 'Periksa status ZIP', exact: true }).click();
    await expect(client.getByText('Status ZIP belum diperbarui.', { exact: false })).toBeVisible();
    await expect(client.getByRole('link', { name: 'Unduh ZIP lengkap', exact: true })).toHaveCount(0);
    await client.unroute('**/delivery/archive');
    fixture({ action: 'expire-archive', email });
    expect((await context.request.get(url)).status()).toBe(404);
    await client.getByRole('button', { name: 'Periksa status ZIP', exact: true }).click();
    await expect(client.getByText('ZIP kedaluwarsa.', { exact: false })).toBeVisible();
    await client.getByRole('button', { name: 'Buat ulang ZIP', exact: true }).click();
    fixture({ action: 'work-archive', email });
    await client.getByRole('button', { name: 'Periksa status ZIP', exact: true }).click();
    await expect(client.getByRole('link', { name: 'Unduh ZIP lengkap', exact: true })).toBeVisible();
    expect(await client.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await client.screenshot({ path: `test-results/archive-${width}-${theme}.png`, fullPage: true });
    fixture({ action: 'close-delivery', email });
    expect((await context.request.get(url, { headers: { Range: 'bytes=4-20' } })).status()).toBe(404);
    await client.getByRole('button', { name: 'Periksa status ZIP', exact: true }).click();
    await expect(client.getByRole('link', { name: 'Unduh ZIP lengkap', exact: true })).toHaveCount(0);
    fixture({ action: 'cleanup-archives', email });
    await context.close();
  });
}
