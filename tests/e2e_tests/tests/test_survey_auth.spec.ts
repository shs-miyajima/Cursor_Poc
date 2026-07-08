// 認証（E2E-001〜E2E-010）— 出典: docs/specs/survey-system/03-test-plan.csv
import { test, expect } from '@playwright/test';
import {
  PASSWORD,
  SU_EMAIL,
  SU_PASSWORD,
  login,
  loginAsSU,
  logout,
  registerUser,
  setupAdmin,
  setupCompany,
  setupUser,
  uid,
} from './helpers';

test('E2E-001-trn ログイン(スーパーユーザー) 全体ビューに遷移しサマリが表示される', async ({ page }) => {
  await login(page, '', SU_EMAIL, SU_PASSWORD);

  await expect(page).toHaveURL(/\/admin\/home$/);
  await expect(page.getByText('企業数')).toBeVisible();
  await expect(page.getByText('アンケート数')).toBeVisible();
  await expect(page.getByText('回答数')).toBeVisible();
});

test('E2E-002-trn ログイン(管理者) ダッシュボードに遷移する', async ({ page }) => {
  const { companyCode, adminEmail } = await setupAdmin(page, 'e2e002');
  await logout(page);

  await login(page, companyCode, adminEmail, PASSWORD);

  await expect(page).toHaveURL(/\/dashboard$/);
});

test('E2E-003-trn ログイン(ユーザー) アンケート一覧に遷移する', async ({ page }) => {
  const { companyCode, userEmail } = await setupUser(page, 'e2e003');
  await logout(page);

  await login(page, companyCode, userEmail, PASSWORD);

  await expect(page).toHaveURL(/\/my\/surveys$/);
});

test('E2E-004-inp ログイン(パスワード不一致) エラーが表示されログイン画面に留まる', async ({ page }) => {
  const { companyCode, adminEmail } = await setupAdmin(page, 'e2e004');
  await logout(page);

  await login(page, companyCode, adminEmail, 'wrongpass99');

  await expect(page.getByTestId('form-errors')).toContainText('ログイン情報が正しくありません');
  await expect(page).toHaveURL(/\/login$/);
});

test('E2E-005-inp ログイン(存在しない企業コード) ログインできない', async ({ page }) => {
  const { adminEmail } = await setupAdmin(page, 'e2e005');
  await logout(page);

  await login(page, 'wrong99', adminEmail, PASSWORD);

  await expect(page.getByTestId('form-errors')).toContainText('ログイン情報が正しくありません');
  await expect(page).toHaveURL(/\/login$/);
});

test('E2E-006-inp ログイン(メール未入力) 必須エラーが表示される', async ({ page }) => {
  await login(page, '', '', 'pass12345');

  await expect(page.getByTestId('form-errors')).toContainText('メールアドレスは必須です');
});

test('E2E-007-inp ログイン(パスワード未入力) 必須エラーが表示される', async ({ page }) => {
  await login(page, '', 'someone@example.com', '');

  await expect(page.getByTestId('form-errors')).toContainText('パスワードは必須です');
});

test('E2E-008-trn ログアウトでログイン画面に遷移する', async ({ page }) => {
  await setupAdmin(page, 'e2e008');

  await logout(page);

  await expect(page).toHaveURL(/\/login$/);
});

test('E2E-009-auth 未ログインアクセスはログイン画面にリダイレクトされる', async ({ page }) => {
  await page.goto('/dashboard');

  await expect(page).toHaveURL(/\/login$/);
});

test('E2E-010-inp ログイン(メール大文字) 小文字正規化により認証成功する', async ({ page }) => {
  const fixture = await setupCompany(page, 'e2e010');
  const local = uid('taro');
  await registerUser(page, {
    companyName: fixture.companyName,
    role: 'user',
    name: '大文字太郎',
    email: `${local}@example.com`,
  });
  await logout(page);

  await login(page, fixture.companyCode, `${local.toUpperCase()}@EXAMPLE.COM`, PASSWORD);

  await expect(page).toHaveURL(/\/my\/surveys$/);
});
