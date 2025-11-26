// @ts-check
const { defineConfig, devices } = require('@playwright/test');

/**
 * Конфигурация Playwright для тестирования
 */
module.exports = defineConfig({
  testDir: './tests',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: 'html',
  use: {
    baseURL: 'http://localhost',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],

  webServer: {
    command: 'echo "Убедитесь, что веб-сервер запущен на http://localhost"',
    port: 80,
    reuseExistingServer: !process.env.CI,
  },
});

