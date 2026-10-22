import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/Browser', testMatch: 'delivery.spec.ts', workers: 1,
  projects: [{ name: 'delivery-flow' }],
  use: { baseURL: 'http://127.0.0.1:18767', browserName: 'chromium' },
  webServer: {
    command: 'php -S 127.0.0.1:18767 -t public tests/Support/delivery-browser-router.php',
    url: 'http://127.0.0.1:18767/up', reuseExistingServer: false,
    env: { APP_ENV: 'testing', APP_URL: 'http://127.0.0.1:18767', APP_DEBUG: 'false',
      DB_CONNECTION: 'pgsql', DB_DATABASE: 'snappik_test', DB_URL: '',
      AUTH_MAILER: 'array', MAIL_MAILER: 'array', SESSION_DRIVER: 'database', CACHE_STORE: 'database' },
  },
});
