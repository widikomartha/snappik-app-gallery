import { expect, test } from '@playwright/test';

for (const width of [360, 1440]) for (const theme of ['light', 'dark'] as const) {
  test(`public legal and support links ${width} ${theme}`, async ({ page }, info) => {
    test.skip(!info.config.configFile?.endsWith('playwright.release.config.ts'), 'Dedicated local release fixture only.');
    await page.setViewportSize({ width, height: 1000 });
    await page.emulateMedia({ colorScheme: theme });
    for (const path of ['/', '/login', '/register']) {
      await page.goto(path);
      const nav = page.getByRole('navigation', { name: 'Informasi dan bantuan' });
      await expect(nav.getByRole('link', { name: 'Ketentuan layanan' })).toHaveAttribute('href', 'https://legal.example.test/terms');
      await expect(nav.getByRole('link', { name: 'Privasi', exact: true })).toHaveAttribute('href', 'https://legal.example.test/privacy');
      const support = nav.getByRole('link', { name: 'Bantuan', exact: true });
      await expect(support).toHaveAttribute('href', 'https://help.example.test/support');
      await support.focus();
      await expect(support).toBeFocused();
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    }
    await page.screenshot({ path: `test-results/release-${width}-${theme}.png`, fullPage: true });
  });
}
