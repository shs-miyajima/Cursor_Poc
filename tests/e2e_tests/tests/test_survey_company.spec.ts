// 企業管理・全体/企業ビュー（E2E-011〜E2E-029）— 出典: docs/specs/survey-system/03-test-plan.csv
import { test, expect } from '@playwright/test';
import {
  PASSWORD,
  answerSurvey,
  createCompany,
  createSurvey,
  login,
  loginAsSU,
  logout,
  registerUser,
  setupCompany,
  switchToCompany,
  uid,
} from './helpers';

test('E2E-011-evt 企業登録(正常) 一覧とユーザー登録画面の企業選択肢に表示される', async ({ page }) => {
  await loginAsSU(page);
  const code = uid('e011');
  const name = `企業${code}`;

  await createCompany(page, name, code);

  const row = page.locator('tbody tr', { hasText: name });
  await expect(row).toContainText(code);
  await page.goto('/users');
  await expect(page.locator('#company_id option', { hasText: name })).toHaveCount(1);
});

test('E2E-012-inp 企業登録(企業名未入力) 必須エラーで追加されない', async ({ page }) => {
  await loginAsSU(page);
  await page.goto('/companies');
  const code = uid('e012');

  await page.fill('#code', code);
  await page.getByRole('button', { name: '登録', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('企業名は必須です');
  await expect(page.locator('tbody tr', { hasText: code })).toHaveCount(0);
});

test('E2E-021-evt 企業編集(名称変更) 一覧が新しい名称で表示される', async ({ page }) => {
  const { companyName } = await setupCompany(page, 'e2e021');
  const newName = `企業${uid('e021new')}`;
  await page.goto('/companies');

  await page.locator('tbody tr', { hasText: companyName }).getByRole('link', { name: '編集' }).click();
  await page.fill('#name', newName);
  await page.getByRole('button', { name: '保存' }).click();

  await expect(page).toHaveURL(/\/companies$/);
  await expect(page.locator('tbody tr', { hasText: newName })).toBeVisible();
  await expect(page.locator('tbody tr', { hasText: companyName })).toHaveCount(0);
});

test('E2E-022-inp 企業編集(名称空更新) 必須エラーで名称は変わらない', async ({ page }) => {
  const { companyName } = await setupCompany(page, 'e2e022');
  await page.goto('/companies');

  await page.locator('tbody tr', { hasText: companyName }).getByRole('link', { name: '編集' }).click();
  await page.fill('#name', '');
  await page.getByRole('button', { name: '保存' }).click();

  await expect(page.getByTestId('form-errors')).toContainText('企業名は必須です');
  await page.goto('/companies');
  await expect(page.locator('tbody tr', { hasText: companyName })).toBeVisible();
});

test('E2E-023-evt 企業削除で一覧から行が消える', async ({ page }) => {
  const { companyName } = await setupCompany(page, 'e2e023');
  await page.goto('/companies');
  page.on('dialog', (dialog) => dialog.accept());

  await page.locator('tbody tr', { hasText: companyName }).getByRole('button', { name: '削除' }).click();

  await expect(page.locator('tbody tr', { hasText: companyName })).toHaveCount(0);
});

test('E2E-026-dsp 全体ビューにサマリ 3 指標と企業一覧が表示される', async ({ page }) => {
  const fixture = await setupCompany(page, 'e2e026');
  const userEmail = `user-${fixture.companyCode}@example.com`;
  await registerUser(page, {
    companyName: fixture.companyName,
    role: 'user',
    name: '回答者',
    email: userEmail,
  });
  await switchToCompany(page, fixture.companyName);
  await createSurvey(page, { title: `サマリ用${fixture.companyCode}` });
  await logout(page);
  await login(page, fixture.companyCode, userEmail, PASSWORD);
  await answerSurvey(page, `サマリ用${fixture.companyCode}`, '満足');
  await logout(page);
  await loginAsSU(page);

  await page.goto('/admin/home');

  await expect(page.getByTestId('summary-companies')).not.toHaveText('0');
  await expect(page.getByTestId('summary-surveys')).not.toHaveText('0');
  await expect(page.getByTestId('summary-responses')).not.toHaveText('0');
  const row = page.locator('tbody tr', { hasText: fixture.companyName });
  await expect(row.getByRole('button', { name: '企業ビューへ切替' })).toBeVisible();
});

test('E2E-027-evt 企業ビュー切替でヘッダーに企業名が表示される', async ({ page }) => {
  const fixture = await setupCompany(page, 'e2e027');
  await registerUser(page, {
    companyName: fixture.companyName,
    role: 'user',
    name: '所属ユーザー',
    email: `user-${fixture.companyCode}@example.com`,
  });

  await switchToCompany(page, fixture.companyName);

  await expect(page).toHaveURL(/\/dashboard$/);
  await expect(page.getByTestId('selected-company')).toContainText(fixture.companyName);
});
