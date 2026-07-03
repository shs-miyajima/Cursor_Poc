import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: 'http://localhost:8000',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      // 社内ネットワークの SSL 制約で Playwright 用ブラウザをダウンロードできないため、
      // インストール済みの Google Chrome を使用する（channel 指定）
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        channel: 'msedge',
        launchOptions: { args: ['--no-sandbox'] },
      },
    },
  ],
});
