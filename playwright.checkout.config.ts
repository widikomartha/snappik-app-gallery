import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/Browser', testMatch: 'checkout.spec.ts', workers: 1,
  projects: [{ name: 'checkout-flow' }],
  use: { baseURL: 'http://127.0.0.1:18770', browserName: 'chromium' },
  webServer: {
    command: 'php -S 127.0.0.1:18770 -t public tests/Support/checkout-browser-router.php',
    url: 'http://127.0.0.1:18770/up', reuseExistingServer: false,
    env: { APP_ENV: 'testing', APP_URL: 'http://127.0.0.1:18770', APP_DEBUG: 'false',
      DB_CONNECTION: 'pgsql', DB_DATABASE: 'snappik_test', DB_URL: '',
      AUTH_MAILER: 'array', MAIL_MAILER: 'array', SESSION_DRIVER: 'database', CACHE_STORE: 'database' },
  },
});
