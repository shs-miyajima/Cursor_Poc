// 既存画面の回帰確認（E2E-162, E2E-163）— 出典: docs/specs/survey-system/03-test-plan.csv
import { test, expect } from '@playwright/test';

test('E2E-162-dsp 回帰(トップページ) タイトルに Cursor_Poc が含まれる', async ({ page }) => {
  await page.goto('/');

  await expect(page).toHaveTitle(/Cursor_Poc/);
});

test('E2E-163-trn 回帰(トップのログインリンク) ログイン画面に遷移する', async ({ page }) => {
  await page.goto('/');

  await page.getByRole('link', { name: 'Log in' }).click();

  await expect(page).toHaveURL(/\/login$/);
});
