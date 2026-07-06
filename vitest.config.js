import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    environment: 'jsdom',
    include: ['resources/js/**/*.test.js'],
    coverage: {
      provider: 'v8',
      // テスト対象とした JS モジュール（テストから読み込まれたファイル）を計測する。
      // エントリ・DOM 配線（app.js / bootstrap.js / ページエントリ）は E2E の担当のため対象外
      thresholds: {
        lines: 90,
        functions: 90,
        branches: 90,
        statements: 90,
      },
    },
  },
});
