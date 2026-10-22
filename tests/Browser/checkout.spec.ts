import { expect, test } from '@playwright/test';
import { spawnSync } from 'node:child_process';

function fixture(input: object) {
  const result = spawnSync('php', ['tests/Support/checkout-browser-fixture.php'], {
    input: JSON.stringify(input), encoding: 'utf8', timeout: 30000,
    env: { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'pgsql', DB_DATABASE: 'snappik_test', DB_URL: '', CACHE_STORE: 'array', AUTH_MAILER: 'array' },
  });
  expect(result.status, 'Isolated checkout fixture').toBe(0);
}

for (const width of [360, 1440]) for (const theme of ['light', 'dark'] as const) {
  test(`checkout survives timeout, lost reply, reload and voucher deactivation ${width}px ${theme}`, async ({ page }, info) => {
    test.skip(info.project.name !== 'checkout-flow', 'Dedicated synthetic test transport only.');
    test.setTimeout(60000);
    await page.setViewportSize({ width, height: 1000 });
    await page.emulateMedia({ colorScheme: theme, reducedMotion: width === 360 ? 'reduce' : 'no-preference' });
    const errors: string[] = [];
    page.on('pageerror', e => errors.push(e.message));
    const suffix = `${Date.now()}-${width}-${theme}`;
    const email = `browser-checkout-${suffix}@example.test`;
    const code = `CHECKOUT-${suffix}`.toUpperCase();
    fixture({ action: 'seed', email, code });
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill(email);
    await page.getByLabel('Kata sandi', { exact: true }).fill('BrowserCheckoutPassword123');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await page.getByRole('link', { name: 'Langganan dan tagihan', exact: true }).click();
    await expect(page.getByRole('region', { name: 'Riwayat pesanan' })).toContainText('Belum ada pesanan');
    await page.getByLabel('Kode voucher (opsional)').fill('INVALID');
    await page.getByRole('button', { name: 'Periksa harga', exact: true }).click();
    await expect(page.getByRole('alert')).toContainText('Voucher tidak tersedia');
    await expect(page.getByLabel('Kode voucher (opsional)')).toHaveValue('INVALID');
    await page.getByLabel('Kode voucher (opsional)').fill(` ${code.toLowerCase()} `);
    await page.getByRole('button', { name: 'Periksa harga', exact: true }).click();
    const totals = page.getByRole('region', { name: 'Harga terkonfirmasi' });
    await expect(totals).toContainText(/Rp\s*29\.000/);
    await expect(totals).toContainText(/Rp\s*2\.900/);
    await expect(totals).toContainText(/Rp\s*26\.100/);
    await page.getByRole('button', { name: /Aktifkan tema/ }).click();
    await expect(totals).toContainText(/Rp\s*26\.100/);
    await page.getByRole('button', { name: /Aktifkan tema/ }).click();
    await page.reload();
    await expect(totals).toContainText(/Rp\s*26\.100/);
    await page.screenshot({ path: `test-results/checkout-quote-${width}-${theme}.png`, fullPage: true });
    await page.route('**/billing/orders', async route => { await route.fetch(); await route.abort(); });
    await page.getByRole('button', { name: 'Buat atau lanjutkan checkout QRIS' }).click();
    await expect(page.getByRole('alert')).toContainText('Jika sudah mengirim checkout');
    await page.unroute('**/billing/orders');
    await page.reload();
    await expect(page.getByRole('region', { name: 'Riwayat pesanan' }).getByRole('article')).toHaveCount(1);
    await page.getByRole('link', { name: /^Buka pesanan/ }).click();
    await expect(page).toHaveURL(/\/billing\/orders\/[a-f0-9-]+$/);
    const orderUrl = page.url();
    await expect(page.getByRole('status').filter({ hasText: 'Pembuatan checkout' })).toContainText('Reservasi voucher tetap ditahan');
    await page.screenshot({ path: `test-results/checkout-unknown-${width}-${theme}.png`, fullPage: true });
    fixture({ action: 'deactivate', email, code });
    await page.getByRole('link', { name: 'Semua tagihan' }).click();
    await expect(totals).toContainText(/Rp\s*26\.100/);
    await page.getByRole('button', { name: 'Buat atau lanjutkan checkout QRIS' }).click();
    await expect(page).toHaveURL(orderUrl);
    await expect(page.getByRole('link', { name: 'Buka checkout QRIS sandbox' })).toBeVisible();
    await page.reload();
    await expect(page.getByRole('region', { name: 'Pesanan Pro', exact: true })).toContainText(/Rp\s*26\.100/);
    const openUrl = await page.getByRole('link', { name: 'Buka checkout QRIS sandbox' }).getAttribute('href');
    const redirect = await page.request.get(openUrl!, { maxRedirects: 0 });
    expect(redirect.status()).toBe(302);
    expect(redirect.headers().location).toBe('https://app.sandbox.midtrans.com/snap/v2/vtweb/synthetic-browser-token');
    await page.getByRole('button', { name: 'Periksa atau pulihkan pesanan' }).click();
    await expect(page).toHaveURL(orderUrl);
    await expect(page.getByRole('link', { name: 'Buka checkout QRIS sandbox' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: `test-results/checkout-pending-${width}-${theme}.png`, fullPage: true });
    fixture({ action: 'verify', email });
    expect(errors).toEqual([]);
  });
}
