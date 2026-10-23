import { expect, test, type Page } from '@playwright/test';
import { spawnSync } from 'node:child_process';
import { writeFile } from 'node:fs/promises';
import { contrastRatios, controlContrast } from './contrast';

function fixture(action: string, email: string) {
  const result = spawnSync('php', ['tests/Support/delivery-browser-fixture.php'], {
    input: JSON.stringify({ action, email }), encoding: 'utf8', timeout: 30000,
    env: { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'pgsql', DB_DATABASE: 'snappik_test', DB_URL: '', CACHE_STORE: 'array' },
  });
  expect(result.status, 'Isolated synthetic product fixture').toBe(0);
}

async function inspect(page: Page, name: string) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  const text = await contrastRatios(page);
  const controls = await controlContrast(page);
  for (const pair of text) expect(pair.ratio, pair.selector).toBeGreaterThanOrEqual(4.5);
  for (const pair of controls) expect(pair.ratio, `${pair.foreground}/${pair.background}`).toBeGreaterThanOrEqual(3);
  await test.info().attach(`contrast-${name}`, { body: JSON.stringify({ text, controls }), contentType: 'application/json' });
  await writeFile(`test-results/product-contrast-${name}.json`, JSON.stringify({ text, controls }, null, 2));
  await page.screenshot({ path: `test-results/product-${name}.png`, fullPage: true });
}

for (const width of [360, 1440]) for (const theme of ['light', 'dark'] as const) for (const reducedMotion of ['reduce', 'no-preference'] as const) {
  test(`integrated public, drafts, client gates, expiry and ZIP ${width} ${theme} ${reducedMotion}`, async ({ page, browser }, info) => {
    test.skip(info.project.name !== 'product-flow', 'Dedicated synthetic product server only.');
    test.setTimeout(90000);
    const suffix = `${width}-${theme}-${reducedMotion}`;
    const email = `browser-delivery-product-${Date.now()}-${suffix}@example.test`;
    fixture('seed', email);
    await page.setViewportSize({ width, height: 1000 });
    await page.emulateMedia({ colorScheme: theme, reducedMotion });
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto('/');
    await expect(page.getByRole('heading', { name: /hari untuk mencoba/ })).toBeVisible();
    await inspect(page, `public-${suffix}`);
    await page.getByRole('link', { name: 'Masuk akun', exact: true }).click();
    await expect(page.locator('main')).toBeFocused();
    await inspect(page, `auth-${suffix}`);
    await page.getByLabel('Email', { exact: true }).fill(email);
    await page.getByLabel('Kata sandi', { exact: true }).fill('WrongPassword123');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByText('Email atau kata sandi tidak cocok.', { exact: true })).toBeVisible();
    await page.getByLabel('Kata sandi', { exact: true }).fill('BrowserDeliveryPassword123');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Ruang fotografer', exact: true })).toBeVisible();
    await inspect(page, `dashboard-${suffix}`);
    await page.getByRole('link', { name: 'Daftar proyek', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Proyek Anda', exact: true })).toBeVisible();
    await inspect(page, `projects-${suffix}`);
    await page.getByRole('link', { name: 'Synthetic finished gallery', exact: true }).click();
    const share = await page.getByLabel('Tautan klien', { exact: true }).inputValue();
    await inspect(page, `project-settings-${suffix}`);
    const projectUrl = page.url();
    await page.getByLabel('Nama proyek / klien', { exact: true }).fill('Draf tetap tersimpan di halaman');
    await page.getByRole('button', { name: /Aktifkan tema/ }).focus();
    await page.keyboard.press('Enter');
    await expect(page.getByLabel('Nama proyek / klien')).toHaveValue('Draf tetap tersimpan di halaman');
    await page.keyboard.press('Enter');
    page.once('dialog', dialog => dialog.dismiss());
    await page.getByRole('link', { name: 'Daftar proyek', exact: true }).click();
    await expect(page).toHaveURL(projectUrl);
    await expect(page.getByLabel('Nama proyek / klien')).toHaveValue('Draf tetap tersimpan di halaman');
    // Cancelled navigation and interrupted motion leave the form available.
    page.once('dialog', dialog => dialog.accept());
    await page.getByRole('link', { name: 'Daftar proyek', exact: true }).click();
    await expect(page).toHaveURL(/\/projects$/);

    const context = await browser.newContext({ baseURL: 'http://127.0.0.1:18772', viewport: { width, height: 1000 }, colorScheme: theme, reducedMotion });
    const client = await context.newPage();
    client.on('pageerror', error => errors.push(error.message));
    await client.goto(share);
    await client.getByLabel('Kata sandi · Pemilihan foto', { exact: true }).fill('WrongPassword123');
    await client.getByRole('button', { name: 'Buka Pemilihan foto', exact: true }).click();
    await expect(client.getByLabel('Kata sandi · Pemilihan foto')).toHaveAttribute('aria-invalid', 'true');
    await expect(client.getByText('Kata sandi galeri tidak cocok.', { exact: true })).toBeVisible();
    await inspect(client, `password-error-${suffix}`);
    await client.getByLabel('Kata sandi · Pemilihan foto', { exact: true }).fill('Selection123');
    await client.getByRole('button', { name: 'Buka Pemilihan foto', exact: true }).click();
    await client.getByRole('link', { name: 'Buka pemilihan foto', exact: true }).click();
    await expect(client.locator('.photo-tile')).toHaveCount(1);
    await client.getByRole('button', { name: 'Tinjau pilihan', exact: true }).click();
    await expect(client.getByText('Belum ada foto dipilih.', { exact: true })).toBeVisible();
    await inspect(client, `empty-${suffix}`);
    await client.getByRole('button', { name: 'Semua foto', exact: true }).click();
    const choose = client.getByRole('button', { name: /^Pilih / });
    let release!: () => void;
    const barrier = new Promise<void>(resolve => { release = resolve; });
    await client.route('**/selection/choices', async route => { await barrier; await route.abort(); }, { times: 1 });
    await choose.focus();
    await client.keyboard.press('Enter');
    await expect(client.getByText('Menyimpan…', { exact: true })).toBeVisible();
    await client.getByRole('button', { name: /Aktifkan tema/ }).click();
    await expect(client.getByRole('heading', { name: '0 / 2 foto dipilih' })).toBeVisible();
    await expect(client.getByText('Pilihan tersimpan.', { exact: true })).toHaveCount(0);
    release();
    await expect(client.getByText('Pilihan terakhir belum bisa dipastikan tersimpan.', { exact: false })).toBeVisible();
    await inspect(client, `failed-save-${suffix}`);
    await client.getByRole('button', { name: 'Coba simpan lagi', exact: true }).click();
    await expect(client.getByRole('heading', { name: '1 / 2 foto dipilih' })).toBeVisible();
    await client.getByRole('button', { name: /Aktifkan tema/ }).click();
    const zoom = client.getByRole('button', { name: /^Perbesar / });
    await expect(zoom).toBeEnabled();
    await zoom.focus(); await client.keyboard.press('Enter');
    await expect(client.getByRole('dialog')).toBeVisible();
    await client.keyboard.press('Shift+Tab');
    expect(await client.getByRole('dialog').evaluate(el => el.contains(document.activeElement))).toBe(true);
    await client.keyboard.press('Escape');
    await expect(zoom).toBeFocused();
    fixture('freeze-account', email);
    await client.getByRole('button', { name: 'Muat ulang pilihan', exact: true }).click();
    await expect(client.getByText('Masa akses fotografer berakhir. Pilihan tersimpan hanya dapat dibaca.', { exact: true })).toBeVisible();
    await expect(client.getByRole('button', { name: /^Hapus pilihan / })).toBeDisabled();
    await inspect(client, `frozen-${suffix}`);

    await page.goto('/dashboard');
    await page.getByRole('link', { name: 'Lihat hasil dan salin nama berkas', exact: true }).click();
    await page.getByRole('button', { name: 'Siapkan daftar nama berkas', exact: true }).click();
    await expect(page.getByRole('textbox', { name: /Daftar nama berkas/ })).not.toHaveValue('');
    await inspect(page, `expired-results-${suffix}`);
    await page.getByRole('link', { name: 'Tagihan', exact: true }).click();
    await expect(page.getByRole('region', { name: 'Riwayat pesanan', exact: true })).toBeVisible();
    await inspect(page, `billing-${suffix}`);

    await client.getByRole('link', { name: 'Kembali ke galeri', exact: true }).click();
    await client.getByLabel('Kata sandi · Hasil akhir', { exact: true }).fill('Delivery123');
    await expect(client.getByLabel('Kata sandi · Hasil akhir', { exact: true })).toHaveValue('Delivery123');
    const unlocked = client.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith('/delivery/password'));
    await client.getByRole('button', { name: 'Buka Hasil akhir', exact: true }).click();
    const unlockResponse = await unlocked;
    expect(unlockResponse.status()).toBe(302);
    expect(unlockResponse.request().postDataJSON().gallery_password).toBe('Delivery123');
    expect(unlockResponse.headers().location).toBe(share);
    await client.getByRole('link', { name: 'Buka hasil akhir', exact: true }).click();
    await expect(client.locator('.photo-tile')).toHaveCount(2);
    const photo = client.locator('.photo-tile img').first();
    await expect(photo).toBeVisible();
    const photoState = () => photo.evaluate(element => {
      const image = element as HTMLImageElement;
      const canvas = document.createElement('canvas');
      canvas.width = image.naturalWidth; canvas.height = image.naturalHeight;
      canvas.getContext('2d')!.drawImage(image, 0, 0);
      const style = getComputedStyle(image);
      return { pixels: canvas.toDataURL(), src: image.src, filter: style.filter, opacity: style.opacity, blend: style.mixBlendMode, fit: style.objectFit };
    });
    await expect(photo).toHaveJSProperty('complete', true);
    const pixels = await photoState();
    expect(pixels).toMatchObject({ filter: 'none', opacity: '1', blend: 'normal', fit: 'contain' });
    await client.getByRole('button', { name: /Aktifkan tema/ }).click();
    expect(await photoState(), 'Photo pixels and rendering unchanged by theme').toEqual(pixels);
    await client.getByRole('button', { name: /Aktifkan tema/ }).click();
    await client.getByRole('button', { name: 'Siapkan ZIP semua foto', exact: true }).click();
    await expect(client.getByText('ZIP menunggu antrean.', { exact: true })).toBeVisible();
    await client.getByRole('button', { name: /Aktifkan tema/ }).click();
    await expect(client.getByText('ZIP menunggu antrean.', { exact: true })).toBeVisible();
    fixture('work-archive', email);
    await client.getByRole('button', { name: 'Periksa status ZIP', exact: true }).click();
    await expect(client.getByRole('link', { name: 'Unduh ZIP lengkap', exact: true })).toHaveAttribute('href', /\/delivery\/archive\//);
    await client.getByRole('button', { name: /Aktifkan tema/ }).click();
    await inspect(client, `delivery-${suffix}`);
    fixture('cleanup-archives', email);
    fixture('close-delivery', email);
    await client.reload();
    await expect(client.getByRole('heading', { name: 'Halaman tidak tersedia', exact: true })).toBeVisible();
    await inspect(client, `closed-${suffix}`);
    expect(errors).toEqual([]);
    await context.close();
  });
}
