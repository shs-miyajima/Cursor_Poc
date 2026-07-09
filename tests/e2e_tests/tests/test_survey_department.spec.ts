// 部署管理（E2E-054〜E2E-061, E2E-176）— 出典: docs/specs/survey-system/03-test-plan.csv
import { test, expect } from '@playwright/test';
import {
  createSurvey,
  registerUser,
  setupAdmin,
  uid,
} from './helpers';

async function addDepartment(page, name: string): Promise<void> {
  await page.goto('/departments');
  await page.fill('#name', name);
  await page.getByRole('button', { name: '登録', exact: true }).click();
}

test('E2E-054-evt 部署登録(正常) 一覧・ユーザー登録・ダッシュボードの選択肢に表示される', async ({ page }) => {
  await setupAdmin(page, 'e2e054');
  const deptName = `部署${uid('e054')}`;

  await addDepartment(page, deptName);

  await expect(page.locator('tbody tr', { hasText: deptName })).toBeVisible();
  await page.goto('/users');
  await expect(page.locator('#department_id option', { hasText: deptName })).toHaveCount(1);
  // ダッシュボードの絞り込み UI は公開アンケートがあるときのみ表示される（S-08）
  await createSurvey(page, { title: `部署確認${uid('e054s')}`, action: 'publish' });
  await page.goto('/dashboard');
  await expect(page.locator('#filter-department option', { hasText: deptName })).toHaveCount(1);
});

test('E2E-055-inp 部署登録(名称未入力) 必須エラーで追加されない', async ({ page }) => {
  await setupAdmin(page, 'e2e055');
  await page.goto('/departments');
  const before = await page.locator('tbody tr').count();

  await page.getByRole('button', { name: '登録', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('部署名は必須です');
  await expect(page.locator('tbody tr')).toHaveCount(before);
});

test('E2E-060-inp 部署編集(名称空更新) 必須エラーで名称は変わらない', async ({ page }) => {
  await setupAdmin(page, 'e2e060');
  await addDepartment(page, '名称A');
  await page.goto('/departments');

  await page.locator('tbody tr', { hasText: '名称A' }).getByRole('link', { name: '編集' }).click();
  await page.fill('#name', '');
  await page.getByRole('button', { name: '保存' }).click();

  await expect(page.getByTestId('form-errors')).toContainText('部署名は必須です');
  await page.goto('/departments');
  await expect(page.locator('tbody tr', { hasText: '名称A' })).toBeVisible();
});

test('E2E-061-evt 部署削除で選択肢から消え所属ユーザーは未設定になる', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e061');
  await addDepartment(page, '廃止部署');
  const email = `orphan-${fixture.companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: '所属ユーザー', email, departmentName: '廃止部署' });
  await page.goto('/departments');
  page.on('dialog', (dialog) => dialog.accept());

  await page.locator('tbody tr', { hasText: '廃止部署' }).getByRole('button', { name: '削除' }).click();

  await expect(page.locator('tbody tr', { hasText: '廃止部署' })).toHaveCount(0);
  await page.goto('/users');
  await expect(page.locator('#department_id option', { hasText: '廃止部署' })).toHaveCount(0);
  await expect(page.locator('tbody tr', { hasText: email })).toContainText('未設定');
});
