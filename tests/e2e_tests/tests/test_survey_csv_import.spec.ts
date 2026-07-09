// ユーザー CSV 取込（E2E-062〜E2E-083, E2E-165, E2E-179〜E2E-182）
// 出典: docs/specs/survey-system/03-test-plan.csv
import { test, expect } from '@playwright/test';
import {
  CSV_HEADER,
  PASSWORD,
  login,
  loginAsSU,
  logout,
  registerUser,
  setupAdmin,
  setupCompany,
  switchToCompany,
  uploadCsv,
} from './helpers';

function rowsCsv(rows: string[]): string {
  return [CSV_HEADER, ...rows].join('\n');
}

async function addDepartment(page, name: string): Promise<void> {
  await page.goto('/departments');
  await page.fill('#name', name);
  await page.getByRole('button', { name: '登録', exact: true }).click();
}

test('E2E-062-evt CSV 取込(全行新規) 確認画面経由で 3 名追加され完了メッセージが出る', async ({ page }) => {
  const { companyCode } = await setupAdmin(page, 'e2e062');
  await addDepartment(page, '営業部');
  const emails = [1, 2, 3].map((i) => `csv${i}-${companyCode}@example.com`);

  await uploadCsv(page, rowsCsv([
    `取込一郎,${emails[0]},pass12345,営業部,男性,1990-01-01,2015-04`,
    `取込二郎,${emails[1]},pass12345,,女性,,`,
    `取込三郎,${emails[2]},pass12345,,,,`,
  ]));

  await expect(page).toHaveURL(/\/users\/import\/confirm$/);
  await expect(page.getByTestId('import-summary')).toHaveText('新規 3 件・更新 0 件');
  for (const line of [1, 2, 3]) {
    await expect(page.getByTestId(`import-row-${line}`)).toContainText('新規');
  }
  await page.getByRole('button', { name: '確定' }).click();

  await expect(page).toHaveURL(/\/users$/);
  await expect(page.getByTestId('flash-success')).toContainText('新規 3 件・更新 0 件');
  for (const email of emails) {
    await expect(page.locator('tbody tr', { hasText: email })).toBeVisible();
  }
});

test('E2E-066-inp CSV(ファイル未選択) 選択エラーが表示される', async ({ page }) => {
  await setupAdmin(page, 'e2e066');
  await page.goto('/users/import');

  await page.getByRole('button', { name: 'アップロード' }).click();

  await expect(page.getByTestId('form-errors')).toContainText('CSV ファイル(2MB 以内)を選択してください');
});
