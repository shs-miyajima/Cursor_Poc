// 部署管理（E2E-054〜E2E-061, E2E-176）— 出典: docs/specs/survey-system/03-test-plan.csv
import { test, expect } from '@playwright/test';
import {
  createCompany,
  loginAsSU,
  registerUser,
  setupAdmin,
  setupCompany,
  switchToCompany,
  uid,
} from './helpers';

async function addDepartment(page, name: string): Promise<void> {
  await page.goto('/departments');
  await page.fill('#name', name);
  await page.getByRole('button', { name: '登録', exact: true }).click();
}

test('E2E-054-inp 部署登録(正常) 一覧・ユーザー登録・ダッシュボードの選択肢に表示される', async ({ page }) => {
  await setupAdmin(page, 'e2e054');
  const deptName = `部署${uid('e054')}`;

  await addDepartment(page, deptName);

  await expect(page.locator('tbody tr', { hasText: deptName })).toBeVisible();
  await page.goto('/users');
  await expect(page.locator('#department_id option', { hasText: deptName })).toHaveCount(1);
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

test('E2E-056-inp 部署登録(101文字) 文字数エラーで追加されない', async ({ page }) => {
  await setupAdmin(page, 'e2e056');
  await page.goto('/departments');

  await page.fill('#name', 'あ'.repeat(101));
  await page.getByRole('button', { name: '登録', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('部署名は 100 文字以内で入力してください');
  await expect(page.locator('tbody tr')).toHaveCount(0);
});

test('E2E-057-inp 部署登録(同一企業内重複) 重複エラーで追加されない', async ({ page }) => {
  await setupAdmin(page, 'e2e057');
  await addDepartment(page, '営業部');

  await addDepartment(page, '営業部');

  await expect(page.getByTestId('form-errors')).toContainText('この部署名は既に登録されています');
  await expect(page.locator('tbody tr', { hasText: '営業部' })).toHaveCount(1);
});

test('E2E-058-inp 部署登録(別企業なら同名可) 両企業の一覧に表示される', async ({ page }) => {
  const a = await setupCompany(page, 'e2e058a');
  await switchToCompany(page, a.companyName);
  await addDepartment(page, '営業部');
  await expect(page.locator('tbody tr', { hasText: '営業部' })).toBeVisible();

  // 企業 B を作成し、B の企業ビューで同名を登録する
  const codeB = uid('e058b');
  const nameB = `企業${codeB}`;
  await createCompany(page, nameB, codeB);
  await switchToCompany(page, nameB);
  await addDepartment(page, '営業部');

  await expect(page.locator('tbody tr', { hasText: '営業部' })).toHaveCount(1);
  // 企業 A 側にも残っている
  await switchToCompany(page, a.companyName);
  await page.goto('/departments');
  await expect(page.locator('tbody tr', { hasText: '営業部' })).toHaveCount(1);
});

test('E2E-059-evt 部署編集(名称変更) 各画面の表示が新名になる', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e059');
  await addDepartment(page, '旧名');
  const email = `dept-${fixture.companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: '所属ユーザー', email, departmentName: '旧名' });
  await page.goto('/departments');

  await page.locator('tbody tr', { hasText: '旧名' }).getByRole('link', { name: '編集' }).click();
  await page.fill('#name', '新名');
  await page.getByRole('button', { name: '保存' }).click();

  await expect(page.locator('tbody tr', { hasText: '新名' })).toBeVisible();
  await page.goto('/users');
  await expect(page.locator('tbody tr', { hasText: email })).toContainText('新名');
  await page.goto('/dashboard');
  await expect(page.locator('#filter-department option', { hasText: '新名' })).toHaveCount(1);
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

test('E2E-176-inp 部署登録(100文字境界) 登録成功し一覧に表示される', async ({ page }) => {
  await setupAdmin(page, 'e2e176');
  const name = 'あ'.repeat(100);

  await addDepartment(page, name);

  await expect(page.locator('tbody tr', { hasText: name })).toBeVisible();
});
