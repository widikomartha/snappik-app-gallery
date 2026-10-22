import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/Browser', testMatch: 'vouchers.spec.ts', workers: 1,
  projects: [{ name: 'voucher-flow' }],
  use: { baseURL: 'http://127.0.0.1:18769', browserName: 'chromium' },
  webServer: {
    command: 'php artisan serve --host=127.0.0.1 --port=18769 --no-reload',
    url: 'http://127.0.0.1:18769/up', reuseExistingServer: false,
    env: { APP_ENV: 'testing', APP_URL: 'http://127.0.0.1:18769', APP_DEBUG: 'false',
      DB_CONNECTION: 'pgsql', DB_DATABASE: 'snappik_test', DB_URL: '',
      AUTH_MAILER: 'array', MAIL_MAILER: 'array', SESSION_DRIVER: 'database', CACHE_STORE: 'database' },
  },
});
