import { expect, test } from '@playwright/test';

for (const width of [360, 1440]) {
  for (const theme of ['light', 'dark'] as const) {
    test(`account forms ${width}px ${theme}`, async ({ page }) => {
      await page.setViewportSize({ width, height: 1000 });
      await page.emulateMedia({ colorScheme: theme, reducedMotion: 'reduce' });
      const errors: string[] = [];
      page.on('pageerror', error => errors.push(error.message));
      for (const path of ['/login', '/register', '/forgot-password']) {
        await page.goto(path);
        await expect(page.getByLabel('Email', { exact: true })).toBeVisible();
        await page.getByLabel('Email', { exact: true }).fill('draft@example.test');
        const toggle = page.getByRole('button', { name: /Aktifkan tema/ });
        await toggle.focus();
        await page.keyboard.press('Enter');
        await expect(page.getByLabel('Email', { exact: true })).toHaveValue('draft@example.test');
        await page.keyboard.press('Enter');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await page.screenshot({ path: `test-results/auth-${path.slice(1)}-${width}-${theme}.png`, fullPage: true });
      }
      expect(errors).toEqual([]);
    });
  }
}

test('invalid login shows a named error and recovery gives a generic response', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Email', { exact: true }).fill('absent-browser@example.test');
  await page.getByLabel('Kata sandi', { exact: true }).fill('WrongPassword123');
  await page.getByRole('button', { name: 'Masuk', exact: true }).click();
  await expect(page.getByText('Email atau kata sandi tidak cocok.')).toBeVisible();
  await expect(page.getByLabel('Email', { exact: true })).toHaveAttribute('aria-invalid', 'true');
  await expect(page.getByLabel('Kata sandi', { exact: true })).toHaveValue('');
  await page.getByRole('link', { name: 'Lupa kata sandi?' }).click();
  await expect(page).toHaveURL(/\/forgot-password$/);
  await page.getByLabel('Email', { exact: true }).fill('absent-browser@example.test');
  await page.getByRole('button', { name: 'Kirim tautan pemulihan' }).click();
  await expect(page.getByRole('status')).toContainText('Jika akun tersedia');
});

test('register, resend, logout and login preserve the unverified gate', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'auth-flow', 'Writes synthetic accounts only in the isolated auth test server.');
  const email = `browser-${Date.now()}@example.test`;
  await page.goto('/register');
  await page.getByLabel('Nama', { exact: true }).fill('Synthetic Browser Photographer');
  await page.getByLabel('Email', { exact: true }).fill(email);
  await page.getByLabel('Kata sandi', { exact: true }).fill('BrowserPassword123');
  await page.getByLabel('Ulangi kata sandi').fill('BrowserPassword123');
  await page.getByRole('button', { name: 'Daftar', exact: true }).click();
  await expect(page).toHaveURL(/\/email\/verify$/);
  await expect(page.getByRole('status')).toContainText('Tautan verifikasi telah dikirim');
  await page.reload();
  await expect(page.getByRole('heading', { name: 'Verifikasi email Anda' })).toBeVisible();
  await page.getByRole('button', { name: 'Kirim ulang email verifikasi' }).click();
  await expect(page.getByRole('status')).toContainText('Tautan verifikasi telah dikirim');
  await page.getByRole('button', { name: 'Keluar', exact: true }).click();
  await expect(page).toHaveURL(/\/login$/);
  await page.getByLabel('Email', { exact: true }).fill(email);
  await page.getByLabel('Kata sandi', { exact: true }).fill('BrowserPassword123');
  await page.getByRole('button', { name: 'Masuk', exact: true }).click();
  await expect(page).toHaveURL(/\/email\/verify$/);
  await page.getByRole('button', { name: 'Keluar', exact: true }).click();
  await page.goto('/dashboard');
  await expect(page).toHaveURL(/\/login$/);
});
