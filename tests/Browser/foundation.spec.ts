import { expect, test } from '@playwright/test';
import { contrastRatios } from './contrast';

for (const width of [360, 1440]) {
  for (const theme of ['light', 'dark'] as const) {
    for (const reducedMotion of ['reduce', 'no-preference'] as const) {
    test(`foundation shells ${width}px ${theme} ${reducedMotion}`, async ({ page }) => {
      await page.setViewportSize({ width, height: 1000 });
      await page.emulateMedia({ colorScheme: theme, reducedMotion });
      const failures: string[] = [];
      page.on('pageerror', (error) => failures.push(error.message));
      for (const surface of ['public', 'auth', 'photographer', 'admin', 'client']) {
        await page.goto(`/_foundation/${surface}`);
        await expect(page.getByRole('heading', { name: 'Fondasi antarmuka' })).toBeVisible();
        for (const result of await contrastRatios(page)) {
          expect(result.ratio, result.selector).toBeGreaterThanOrEqual(4.5);
        }
        await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await page.getByLabel('Nama proyek contoh').fill('Draft dipertahankan');
        const toggle = page.getByRole('button', { name: /Aktifkan tema/ });
        await toggle.focus();
        await page.keyboard.press('Enter');
        await expect(page.getByLabel('Nama proyek contoh')).toHaveValue('Draft dipertahankan');
        await page.keyboard.press('Enter');
        await page.getByRole('button', { name: 'Buka dialog contoh' }).click();
        await expect(page.getByRole('dialog')).toBeVisible();
        await page.keyboard.press('Tab');
        expect(await page.getByRole('dialog').evaluate((dialog) => dialog.contains(document.activeElement))).toBe(true);
        await page.keyboard.press('Escape');
        await expect(page.getByRole('dialog')).not.toBeVisible();
        await expect(page.getByRole('button', { name: 'Buka dialog contoh' })).toBeFocused();
        await page.screenshot({ path: `test-results/${surface}-${width}-${theme}-${reducedMotion}.png`, fullPage: true });
      }
      expect(failures).toEqual([]);
    });
    }
  }
}

test('stored theme applies before React and survives reload and Inertia navigation', async ({ page }) => {
  await page.emulateMedia({ colorScheme: 'light', reducedMotion: 'no-preference' });
  await page.goto('/_foundation/public');
  await page.getByRole('button', { name: 'Aktifkan tema gelap' }).click();
  await page.getByRole('link', { name: 'client', exact: true }).click();
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  await page.reload();
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
});

test('stored theme initializes even before the application bundle executes', async ({ page }) => {
  await page.addInitScript(() => localStorage.setItem('snappik.appearance', 'dark'));
  await page.route('**/build/assets/*.js', (route) => route.abort());
  await page.goto('/');
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
});

test('theme and forms remain usable when browser storage is blocked', async ({ page }) => {
  await page.addInitScript(() => {
    Object.defineProperty(window, 'localStorage', { get() { throw new Error('blocked'); } });
  });
  await page.goto('/_foundation/client');
  await page.getByLabel('Nama proyek contoh').fill('Draft');
  await page.getByRole('button', { name: /Aktifkan tema/ }).click();
  await expect(page.getByLabel('Nama proyek contoh')).toHaveValue('Draft');
});
