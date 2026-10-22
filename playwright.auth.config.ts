import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/Browser',
  testMatch: 'auth.spec.ts',
  workers: 1,
  projects: [{ name: 'auth-flow' }],
  use: { baseURL: 'http://127.0.0.1:18763', browserName: 'chromium' },
  webServer: {
    // Dedicated .env.testing connection; no existing server may be reused.
    command: 'php artisan serve --host=127.0.0.1 --port=18763 --no-reload',
    url: 'http://127.0.0.1:18763/up',
    reuseExistingServer: false,
    env: {
      APP_ENV: 'testing', APP_URL: 'http://127.0.0.1:18763', APP_DEBUG: 'false',
      DB_CONNECTION: 'pgsql', DB_DATABASE: 'snappik_test', DB_URL: '',
      AUTH_MAILER: 'array', MAIL_MAILER: 'array', SESSION_DRIVER: 'database', CACHE_STORE: 'database',
    },
  },
});
