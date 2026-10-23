import { expect, test } from '@playwright/test';
import { contrastRatios, controlContrast } from './contrast';

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
        for (const result of await controlContrast(page)) expect(result.ratio, `${result.foreground}/${result.background}`).toBeGreaterThanOrEqual(3);
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
  await expect(page.locator('html')).toHaveCSS('color-scheme', 'dark');
});

test('theme and forms remain usable when browser storage is blocked', async ({ page }) => {
  await page.addInitScript(() => {
    Object.defineProperty(window, 'localStorage', { get() { throw new Error('blocked'); } });
  });
  await page.goto('/_foundation/client');
  await page.getByLabel('Nama proyek contoh').fill('Draft');
  await page.getByRole('button', { name: /Aktifkan tema/ }).click();
  await expect(page.getByLabel('Nama proyek contoh')).toHaveValue('Draft');
  const explicit = await page.locator('html').getAttribute('data-theme');
  await page.emulateMedia({ colorScheme: explicit === 'light' ? 'dark' : 'light' });
  await expect(page.locator('html')).toHaveAttribute('data-theme', explicit!);
  await page.getByRole('link', { name: 'public', exact: true }).click();
  await expect(page.locator('html')).toHaveAttribute('data-theme', explicit!);
});

test('fresh system preference tracks changes until an explicit keyboard choice', async ({ page }) => {
  await page.emulateMedia({ colorScheme: 'dark' });
  await page.goto('/_foundation/public');
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  await page.emulateMedia({ colorScheme: 'light' });
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
  await page.getByRole('button', { name: 'Aktifkan tema gelap', exact: true }).focus();
  await page.keyboard.press('Enter');
  await expect(page.getByRole('button', { name: 'Aktifkan tema terang', exact: true })).toBeFocused();
  await page.emulateMedia({ colorScheme: 'dark' });
  await page.emulateMedia({ colorScheme: 'light' });
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  await page.reload();
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
});

for (const theme of ['light', 'dark'] as const) test(`system ${theme} applies before application scripts`, async ({ page }) => {
  await page.emulateMedia({ colorScheme: theme });
  await page.route('**/build/assets/*.js', route => route.abort());
  await page.goto('/');
  await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
  await expect(page.locator('html')).toHaveCSS('color-scheme', theme);
});

for (const theme of ['light', 'dark'] as const) test(`200 percent reflow, forced colors and interrupted dialogs ${theme}`, async ({ page }) => {
  await page.setViewportSize({ width: 720, height: 1000 });
  await page.emulateMedia({ colorScheme: theme, reducedMotion: 'no-preference', forcedColors: 'active' });
  await page.goto('/_foundation/client');
  await page.evaluate(() => { document.documentElement.style.zoom = '2'; });
  await page.getByLabel('Nama proyek contoh').fill('Draf pada 200%');
  const open = page.getByRole('button', { name: 'Buka dialog contoh', exact: true });
  for (let i = 0; i < 2; i++) {
    await open.focus(); await page.keyboard.press('Enter');
    await expect(page.getByRole('dialog')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(open).toBeFocused();
  }
  await expect(page.getByLabel('Nama proyek contoh')).toHaveValue('Draf pada 200%');
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  await page.screenshot({ path: `test-results/reflow-forced-colors-${theme}.png`, fullPage: true });
});
