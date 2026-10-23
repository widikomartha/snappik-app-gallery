import { expect, test } from '@playwright/test';

for (const width of [360, 1440]) for (const theme of ['light', 'dark'] as const) {
  test(`landing content, choices and theme ${width} ${theme}`, async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.setViewportSize({ width, height: 1000 });
    await page.emulateMedia({ colorScheme: theme, reducedMotion: 'no-preference' });
    await page.goto('/');
    await expect(page.getByRole('heading', { level: 1 })).toContainText('Semua tertata.');
    await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
    const themeControl = page.getByRole('button', { name: /Aktifkan tema/ });
    await expect(themeControl).toHaveText('');
    await expect(themeControl.locator('svg')).toHaveCount(1);
    await expect(page.getByText('Ilustrasi alur · bukan status proyek', { exact: true })).toHaveCount(0);
    await expect(page.locator('.landing-feature-icon')).toHaveCount(4);
    await page.evaluate(() => document.fonts.ready);
    expect(await page.evaluate(() => document.fonts.check('700 32px Manrope'))).toBe(true);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    if (width === 1440) expect(await page.locator('.site-header').evaluate(element => element.getBoundingClientRect().height)).toBeLessThanOrEqual(120);
    const lowContrast = await page.locator('.shell--landing').evaluate(root => {
      const rgb = (value: string) => (value.match(/[\d.]+/g) ?? []).map(Number);
      const luminance = (color: number[]) => color.slice(0, 3).map(value => {
        const channel = value / 255;
        return channel <= .04045 ? channel / 12.92 : ((channel + .055) / 1.055) ** 2.4;
      }).reduce((sum, value, index) => sum + value * [.2126, .7152, .0722][index], 0);
      return [...root.querySelectorAll('p,li,h1,h2,h3,a,summary,.landing-kicker,.landing-photo-label')]
        .filter(element => element.getClientRects().length)
        .flatMap(element => {
          let background: Element | null = element;
          while (background && rgb(getComputedStyle(background).backgroundColor)[3] === 0) background = background.parentElement;
          if (!background) return [];
          const colors = [getComputedStyle(element).color, getComputedStyle(background).backgroundColor].map(value => luminance(rgb(value))).sort((a, b) => b - a);
          const ratio = (colors[0] + .05) / (colors[1] + .05);
          return ratio < 4.5 ? [{ text: element.textContent?.slice(0, 50), ratio }] : [];
        });
    });
    expect(lowContrast).toEqual([]);
    for (const id of ['landing-cara', 'landing-fitur', 'landing-harga', 'landing-faq']) {
      await expect(page.locator(`#${id}`)).toHaveCount(1);
    }
    await expect(page.locator('.landing-price').last()).not.toContainText('undefined');
    for (const link of await page.locator('a.landing-cta').all()) await expect(link).toHaveAttribute('href', '/register');

    const demo = page.getByRole('group', { name: 'Contoh interaktif galeri pilihan' });
    await demo.getByRole('checkbox', { name: 'FOTO_002.jpg' }).check();
    await demo.getByRole('checkbox', { name: 'FOTO_003.jpg' }).check();
    await demo.getByRole('checkbox', { name: 'FOTO_004.jpg' }).click();
    await expect(demo.getByRole('checkbox', { name: 'FOTO_004.jpg' })).not.toBeChecked();
    await expect(demo.getByRole('status')).toContainText('Batas contoh ini 3 foto.');
    await page.getByRole('button', { name: /Aktifkan tema/ }).click();
    await expect(demo.getByRole('checkbox', { checked: true })).toHaveCount(3);
    await page.getByRole('button', { name: /Aktifkan tema/ }).click();
    await demo.getByRole('checkbox', { name: 'FOTO_001.jpg' }).uncheck();
    await demo.getByRole('checkbox', { name: 'FOTO_004.jpg' }).focus();
    await page.keyboard.press('Space');
    await expect(demo.getByRole('checkbox', { name: 'FOTO_004.jpg' })).toBeChecked();

    const question = page.getByText('Apakah kata sandi SnapPik melindungi tautan Drive?', { exact: true });
    await question.click();
    await expect(page.getByText('Siapa pun yang memiliki tautan sumber publik', { exact: false })).toBeVisible();
    await page.getByRole('button', { name: 'Jeda animasi', exact: true }).click();
    await expect(page.locator('.landing-page')).toHaveAttribute('data-motion-paused', 'true');
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await expect(page.locator('.landing-pause')).toHaveCount(0);
    await expect(page.locator('.landing-float').first()).toHaveCSS('animation-name', 'none');
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await expect(page.getByRole('button', { name: 'Putar animasi', exact: true })).toBeVisible();
    await page.screenshot({ path: `test-results/landing-${width}-${theme}.png`, fullPage: true });
    expect(errors).toEqual([]);
  });
}

test('landing CTA opens registration and leaves the operational theme intact', async ({ page }) => {
  await page.goto('/');
  await page.locator('.landing-hero').getByRole('link', { name: 'Mulai uji coba 7 hari' }).click();
  await expect(page).toHaveURL(/\/register$/);
  await expect(page.getByLabel('Email', { exact: true })).toBeVisible();
  await expect(page.locator('.shell--landing')).toHaveCount(0);
  await expect(page.locator('main')).toBeFocused();
});

test('landing reflows at 200 percent and keeps keyboard navigation available', async ({ page }) => {
  await page.setViewportSize({ width: 720, height: 1000 });
  await page.goto('/');
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  await page.evaluate(() => { document.documentElement.style.zoom = '2'; });
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  await page.keyboard.press('Tab');
  await expect(page.getByRole('link', { name: 'Lewati ke konten' })).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page.locator('main')).toBeFocused();
});
