import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/Browser', testMatch: 'product.spec.ts', workers: 1,
  projects: [{ name: 'product-flow' }],
  use: { baseURL: 'http://127.0.0.1:18772', browserName: 'chromium' },
  webServer: {
    command: 'php -S 127.0.0.1:18772 -t public tests/Support/delivery-browser-router.php',
    url: 'http://127.0.0.1:18772/up', reuseExistingServer: false,
    env: { APP_ENV: 'testing', APP_URL: 'http://127.0.0.1:18772', APP_DEBUG: 'false', APP_LOCALE: 'id',
      DB_CONNECTION: 'pgsql', DB_DATABASE: 'snappik_test', DB_URL: '',
      AUTH_MAILER: 'array', MAIL_MAILER: 'array', SESSION_DRIVER: 'database', CACHE_STORE: 'database' },
  },
});
