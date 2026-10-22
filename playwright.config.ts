import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/Browser',
  fullyParallel: false,
  use: {
    baseURL: 'http://127.0.0.1:8000',
    browserName: 'chromium',
    ...(process.env.PLAYWRIGHT_CHANNEL ? { channel: process.env.PLAYWRIGHT_CHANNEL } : {}),
  },
  webServer: {
    command: 'php artisan serve --host=127.0.0.1 --port=8000 --no-reload',
    url: 'http://127.0.0.1:8000/up',
    reuseExistingServer: !process.env.CI,
  },
});
