import { defineConfig, devices } from '@playwright/test';

// エビデンスを機能（docs/specs/<slug>）単位で分けて出力する。
// 例: $env:FEATURE='001_create_management'; npx playwright test tests/test_equipment_loans.spec.ts
// 未指定の場合は 'all' フォルダに出力する
const feature = process.env.FEATURE ?? 'all';

export default defineConfig({
  testDir: './tests',
  // 実行前に必ず `npm run build` でフロントエンド資産を最新化する（ビルド未反映による誤検知を防止）
  globalSetup: require.resolve('./global-setup'),
  outputDir: `test-results/${feature}`,
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: [
    ['list'],
    ['html', { outputFolder: `playwright-report/${feature}`, open: 'never' }],
  ],
  use: {
    baseURL: 'http://localhost:8000',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      // Playwright 版 Chromium を使用する。
      // 社内 SSL 復号プロキシ環境でのダウンロードには NODE_OPTIONS=--use-system-ca が必要
      // （手順: npx playwright install chromium）
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
      },
    },
  ],
});
