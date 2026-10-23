import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/Browser',
  testMatch: 'release.spec.ts',
  workers: 1,
  use: {
    baseURL: 'http://127.0.0.1:18773',
    browserName: 'chromium',
    ...(process.env.PLAYWRIGHT_CHANNEL ? { channel: process.env.PLAYWRIGHT_CHANNEL } : {}),
  },
  webServer: {
    command: 'php artisan serve --host=127.0.0.1 --port=18773 --no-reload',
    url: 'http://127.0.0.1:18773/up',
    reuseExistingServer: false,
    env: {
      APP_ENV: 'testing', APP_URL: 'http://127.0.0.1:18773', DB_CONNECTION: 'pgsql', DB_DATABASE: 'snappik_test', DB_URL: '',
      SESSION_DRIVER: 'array', CACHE_STORE: 'array', MAIL_MAILER: 'array', AUTH_MAILER: 'array',
      SNAPPIK_TERMS_URL: 'https://legal.example.test/terms', SNAPPIK_PRIVACY_URL: 'https://legal.example.test/privacy',
      SNAPPIK_SUPPORT_URL: 'https://help.example.test/support',
    },
  },
});
