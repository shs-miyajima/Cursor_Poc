// ユーザー管理（E2E-030〜E2E-053, E2E-164, E2E-169, E2E-177, E2E-178, E2E-184, E2E-185）
// 出典: docs/specs/survey-system/03-test-plan.csv
import { test, expect } from '@playwright/test';
import {
  PASSWORD,
  createCompany,
  login,
  loginAsSU,
  logout,
  registerUser,
  setupAdmin,
  setupCompany,
  uid,
} from './helpers';

test('E2E-030-evt SU によるユーザー登録後にそのアカウントでログインできる', async ({ page }) => {
  const fixture = await setupCompany(page, 'e2e030');
  const email = `user-${fixture.companyCode}@example.com`;

  await registerUser(page, {
    companyName: fixture.companyName,
    role: 'user',
    name: 'SU登録ユーザー',
    email,
  });
  await logout(page);
  await login(page, fixture.companyCode, email, PASSWORD);

  await expect(page).toHaveURL(/\/my\/surveys$/);
});

test('E2E-032-evt 管理者によるユーザー登録（企業選択欄なしで自社固定）', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e032');
  const email = `member-${fixture.companyCode}@example.com`;

  await page.goto('/users');
  await expect(page.locator('#company_id')).toHaveCount(0);
  await registerUser(page, { role: 'user', name: '管理者登録ユーザー', email });
  await logout(page);
  await login(page, fixture.companyCode, email, PASSWORD);

  await expect(page).toHaveURL(/\/my\/surveys$/);
});

test('E2E-034-inp ユーザー登録(氏名未入力) 必須エラーで登録されない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e034');
  const email = `noname-${fixture.companyCode}@example.com`;
  await page.goto('/users');

  await page.fill('#email', email);
  await page.fill('#password', PASSWORD);
  await page.getByRole('button', { name: '登録', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('氏名は必須です');
  await expect(page.locator('tbody tr', { hasText: email })).toHaveCount(0);
});

test('E2E-045-inp ユーザー編集(氏名空更新) 必須エラーで氏名は変わらない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e045');
  const email = `blank-${fixture.companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: '氏名A', email });

  await page.locator('tbody tr', { hasText: email }).getByRole('link', { name: '編集' }).click();
  await page.fill('#name', '');
  await page.getByRole('button', { name: '保存' }).click();

  await expect(page.getByTestId('form-errors')).toContainText('氏名は必須です');
  await page.goto('/users');
  await expect(page.locator('tbody tr', { hasText: email })).toContainText('氏名A');
});

test('E2E-052-evt ユーザー削除後は一覧から消えログインできない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e052');
  const email = `deleted-${fixture.companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: '削除対象', email });
  page.on('dialog', (dialog) => dialog.accept());

  await page.locator('tbody tr', { hasText: email }).getByRole('button', { name: '削除' }).click();

  await expect(page.locator('tbody tr', { hasText: email })).toHaveCount(0);
  await logout(page);
  await login(page, fixture.companyCode, email, PASSWORD);
  await expect(page.getByTestId('form-errors')).toContainText('ログイン情報が正しくありません');
});

test('E2E-164-auth SU による他社ユーザー編集は保存できる', async ({ page }) => {
  const fixture = await setupCompany(page, 'e2e164');
  const email = `su-edit-${fixture.companyCode}@example.com`;
  await registerUser(page, {
    companyName: fixture.companyName,
    role: 'user',
    name: '編集前氏名',
    email,
  });

  await page.locator('tbody tr', { hasText: email }).getByRole('link', { name: '編集' }).click();
  await page.fill('#name', '編集後氏名');
  await page.getByRole('button', { name: '保存' }).click();

  await expect(page).toHaveURL(/\/users$/);
  await expect(page.locator('tbody tr', { hasText: email })).toContainText('編集後氏名');
});
