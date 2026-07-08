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

test('E2E-011-inp 企業登録(正常) 一覧とユーザー登録画面の企業選択肢に表示される', async ({ page }) => {
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

test('E2E-013-inp 企業登録(企業名重複) 重複エラーで追加されない', async ({ page }) => {
  const { companyName } = await setupCompany(page, 'e2e013');
  await page.goto('/companies');

  await page.fill('#name', companyName);
  await page.fill('#code', uid('e013b'));
  await page.getByRole('button', { name: '登録', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('この企業名は既に登録されています');
  await expect(page.locator('tbody tr', { hasText: companyName })).toHaveCount(1);
});

test('E2E-014-inp 企業登録(企業名101文字) 文字数エラーで追加されない', async ({ page }) => {
  await loginAsSU(page);
  await page.goto('/companies');
  const code = uid('e014');

  await page.fill('#name', 'あ'.repeat(101));
  await page.fill('#code', code);
  await page.getByRole('button', { name: '登録', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('企業名は 100 文字以内で入力してください');
  await expect(page.locator('tbody tr', { hasText: code })).toHaveCount(0);
});

test('E2E-015-inp 企業登録(企業名100文字境界) 登録に成功する', async ({ page }) => {
  await loginAsSU(page);
  await page.goto('/companies');
  const code = uid('e015');
  const name = `企業${code}`.padEnd(100, 'あ');

  await page.fill('#name', name);
  await page.fill('#code', code);
  await page.getByRole('button', { name: '登録', exact: true }).click();

  await expect(page.locator('tbody tr', { hasText: name })).toBeVisible();
});

test('E2E-016-inp 企業登録(コード未入力) 必須エラーで追加されない', async ({ page }) => {
  await loginAsSU(page);
  await page.goto('/companies');
  const name = `企業${uid('e016')}`;

  await page.fill('#name', name);
  await page.getByRole('button', { name: '登録', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('企業コードは必須です');
  await expect(page.locator('tbody tr', { hasText: name })).toHaveCount(0);
});

test('E2E-017-inp 企業登録(コード記号混入) 形式エラーで追加されない', async ({ page }) => {
  await loginAsSU(page);
  await page.goto('/companies');
  const name = `企業${uid('e017')}`;

  await page.fill('#name', name);
  await page.fill('#code', 'abc-01!');
  await page.getByRole('button', { name: '登録', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('企業コードは半角英数字 20 文字以内で入力してください');
  await expect(page.locator('tbody tr', { hasText: name })).toHaveCount(0);
});

test('E2E-018-inp 企業登録(コード21文字) 形式エラーで追加されない', async ({ page }) => {
  await loginAsSU(page);
  await page.goto('/companies');
  const name = `企業${uid('e018')}`;

  await page.fill('#name', name);
  await page.fill('#code', 'a'.repeat(21));
  await page.getByRole('button', { name: '登録', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('企業コードは半角英数字 20 文字以内で入力してください');
  await expect(page.locator('tbody tr', { hasText: name })).toHaveCount(0);
});

test('E2E-019-inp 企業登録(コード20文字境界) 登録に成功する', async ({ page }) => {
  await loginAsSU(page);
  await page.goto('/companies');
  const code = uid('e019').padEnd(20, 'x').slice(0, 20);
  const name = `企業${code}`;

  await page.fill('#name', name);
  await page.fill('#code', code);
  await page.getByRole('button', { name: '登録', exact: true }).click();

  await expect(page.locator('tbody tr', { hasText: code })).toBeVisible();
});

test('E2E-020-inp 企業登録(コード重複) 重複エラーで追加されない', async ({ page }) => {
  const { companyCode } = await setupCompany(page, 'e2e020');
  await page.goto('/companies');
  const name = `企業${uid('e020b')}`;

  await page.fill('#name', name);
  await page.fill('#code', companyCode);
  await page.getByRole('button', { name: '登録', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('この企業コードは既に登録されています');
  await expect(page.locator('tbody tr', { hasText: name })).toHaveCount(0);
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

test('E2E-024-auth 削除済み企業ではログインできない', async ({ page }) => {
  const fixture = await setupCompany(page, 'e2e024');
  const adminEmail = `admin-${fixture.companyCode}@example.com`;
  await registerUser(page, {
    companyName: fixture.companyName,
    role: 'admin',
    name: '削除企業管理者',
    email: adminEmail,
  });
  await page.goto('/companies');
  page.on('dialog', (dialog) => dialog.accept());
  await page.locator('tbody tr', { hasText: fixture.companyName }).getByRole('button', { name: '削除' }).click();
  await expect(page.locator('tbody tr', { hasText: fixture.companyName })).toHaveCount(0);
  await logout(page);

  await login(page, fixture.companyCode, adminEmail, PASSWORD);

  await expect(page.getByTestId('form-errors')).toContainText('ログイン情報が正しくありません');
});

test('E2E-025-dsp 企業編集画面はコード変更不可で企業名のみ編集できる', async ({ page }) => {
  const { companyName, companyCode } = await setupCompany(page, 'e2e025');
  await page.goto('/companies');

  await page.locator('tbody tr', { hasText: companyName }).getByRole('link', { name: '編集' }).click();

  const codeInput = page.locator(`input[value="${companyCode}"]`);
  await expect(codeInput).toBeDisabled();
  await expect(page.locator('#name')).toBeEnabled();
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

test('E2E-028-evt 全体ビューへ戻るとヘッダーの企業名表示が消える', async ({ page }) => {
  const fixture = await setupCompany(page, 'e2e028');
  await switchToCompany(page, fixture.companyName);

  await page.getByRole('button', { name: '全体ビューへ戻る' }).click();

  await expect(page).toHaveURL(/\/admin\/home$/);
  await expect(page.getByTestId('selected-company')).toHaveCount(0);
});

test('E2E-029-dsp 企業ビューの内容が管理者ログイン時と一致する', async ({ page }) => {
  const fixture = await setupCompany(page, 'e2e029');
  const adminEmail = `admin-${fixture.companyCode}@example.com`;
  await registerUser(page, {
    companyName: fixture.companyName,
    role: 'admin',
    name: `管理者${fixture.companyCode}`,
    email: adminEmail,
  });
  await registerUser(page, {
    companyName: fixture.companyName,
    role: 'user',
    name: `一般${fixture.companyCode}`,
    email: `user-${fixture.companyCode}@example.com`,
  });
  // 管理者として部署とアンケートを作成
  await logout(page);
  await login(page, fixture.companyCode, adminEmail, PASSWORD);
  await page.goto('/departments');
  await page.fill('#name', '営業部');
  await page.getByRole('button', { name: '登録', exact: true }).click();
  await createSurvey(page, { title: `内容一致${fixture.companyCode}` });

  // 管理者ビューでの行数を記録
  await page.goto('/users');
  const adminUserRows = await page.locator('tbody tr').count();
  await page.goto('/surveys');
  const adminSurveyRows = await page.locator('tbody tr').count();
  await page.goto('/departments');
  const adminDeptRows = await page.locator('tbody tr').count();
  await logout(page);

  // SU 企業ビューで同じ画面を確認
  await loginAsSU(page);
  await switchToCompany(page, fixture.companyName);
  await page.goto('/users');
  await expect(page.locator('tbody tr')).toHaveCount(adminUserRows);
  await expect(page.locator('tbody tr', { hasText: adminEmail })).toBeVisible();
  await page.goto('/surveys');
  await expect(page.locator('tbody tr')).toHaveCount(adminSurveyRows);
  await expect(page.locator('tbody tr', { hasText: `内容一致${fixture.companyCode}` })).toBeVisible();
  await page.goto('/departments');
  await expect(page.locator('tbody tr')).toHaveCount(adminDeptRows);
  await expect(page.locator('tbody tr', { hasText: '営業部' })).toBeVisible();
});
