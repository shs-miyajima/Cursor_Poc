// 権限・ナビゲーション（E2E-145〜E2E-161, E2E-167, E2E-168, E2E-186〜E2E-191）
// 出典: docs/specs/survey-system/03-test-plan.csv
import { test, expect, Page } from '@playwright/test';
import {
  PASSWORD,
  createSurvey,
  login,
  loginAsSU,
  logout,
  registerUser,
  sendForm,
  setupAdmin,
  setupUser,
  uid,
} from './helpers';

async function expectStatus(page: Page, path: string, status: number): Promise<void> {
  const response = await page.goto(path);
  expect(response?.status()).toBe(status);
}

test('E2E-145-auth 管理者の企業管理アクセスは 403', async ({ page }) => {
  await setupAdmin(page, 'e2e145');
  await expectStatus(page, '/companies', 403);
});

test('E2E-146-auth 管理者の全体ビューアクセスは 403', async ({ page }) => {
  await setupAdmin(page, 'e2e146');
  await expectStatus(page, '/admin/home', 403);
});

test('E2E-147-auth ユーザーのダッシュボードアクセスは 403', async ({ page }) => {
  await setupUser(page, 'e2e147');
  await expectStatus(page, '/dashboard', 403);
});

test('E2E-148-auth ユーザーのアンケート管理アクセスは 403', async ({ page }) => {
  await setupUser(page, 'e2e148');
  await expectStatus(page, '/surveys', 403);
});

test('E2E-149-auth ユーザーのユーザー管理アクセスは 403', async ({ page }) => {
  await setupUser(page, 'e2e149');
  await expectStatus(page, '/users', 403);
});

test('E2E-150-auth ユーザーの企業管理アクセスは 403', async ({ page }) => {
  await setupUser(page, 'e2e150');
  await expectStatus(page, '/companies', 403);
});

test('E2E-151-auth ユーザーの部署管理アクセスは 403', async ({ page }) => {
  await setupUser(page, 'e2e151');
  await expectStatus(page, '/departments', 403);
});

test('E2E-152-auth 他社アンケート結果 API は 403 または 404 になる', async ({ page }) => {
  // 企業 B のアンケートを作成して ID を取得する
  const b = await setupAdmin(page, 'e2e152b');
  const title = `B社結果${b.companyCode}`;
  await createSurvey(page, { title });
  const editHref = await page
    .locator('tbody tr', { hasText: title })
    .getByRole('link', { name: '編集' })
    .getAttribute('href');
  const surveyId = editHref?.match(/\/surveys\/(\d+)\/edit/)?.[1];
  await logout(page);

  // 企業 A の管理者でアクセスする
  await setupAdmin(page, 'e2e152a');
  const response = await page.goto(`/api/surveys/${surveyId}/results`);

  expect([403, 404]).toContain(response?.status());
});

test('E2E-153-auth 他社ユーザー編集画面は 404 になる', async ({ page }) => {
  const b = await setupAdmin(page, 'e2e153b');
  const email = `target-${b.companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: 'B社ユーザー', email });
  const editHref = await page
    .locator('tbody tr', { hasText: email })
    .getByRole('link', { name: '編集' })
    .getAttribute('href');
  await logout(page);

  await setupAdmin(page, 'e2e153a');
  await expectStatus(page, editHref ?? '', 404);
});

test('E2E-154-auth 他社部署編集画面は 404 になる', async ({ page }) => {
  const b = await setupAdmin(page, 'e2e154b');
  await page.goto('/departments');
  await page.fill('#name', `B社部署${b.companyCode}`);
  await page.getByRole('button', { name: '登録', exact: true }).click();
  const editHref = await page
    .locator('tbody tr', { hasText: `B社部署${b.companyCode}` })
    .getByRole('link', { name: '編集' })
    .getAttribute('href');
  await logout(page);

  await setupAdmin(page, 'e2e154a');
  await expectStatus(page, editHref ?? '', 404);
});

test('E2E-155-auth 他社アンケート編集画面は 404 になる', async ({ page }) => {
  const b = await setupAdmin(page, 'e2e155b');
  const title = `B社アンケート${b.companyCode}`;
  await createSurvey(page, { title });
  const editHref = await page
    .locator('tbody tr', { hasText: title })
    .getByRole('link', { name: '編集' })
    .getAttribute('href');
  await logout(page);

  await setupAdmin(page, 'e2e155a');
  await expectStatus(page, editHref ?? '', 404);
});

test('E2E-156-auth 他社アンケート回答画面は 404 になる', async ({ page }) => {
  const b = await setupAdmin(page, 'e2e156b');
  const title = `B社回答${b.companyCode}`;
  await createSurvey(page, { title });
  const editHref = await page
    .locator('tbody tr', { hasText: title })
    .getByRole('link', { name: '編集' })
    .getAttribute('href');
  const surveyId = editHref?.match(/\/surveys\/(\d+)\/edit/)?.[1];
  await logout(page);

  await setupUser(page, 'e2e156a');
  await expectStatus(page, `/my/surveys/${surveyId}`, 404);
});

test('E2E-157-auth SU の回答画面アクセスは 403', async ({ page }) => {
  await loginAsSU(page);
  await expectStatus(page, '/my/surveys', 403);
});

test('E2E-158-auth 管理者の回答画面アクセスは 403', async ({ page }) => {
  await setupAdmin(page, 'e2e158');
  await expectStatus(page, '/my/surveys', 403);
});

test('E2E-159-dsp ナビメニュー(SU 全体ビュー) 企業依存メニューは表示されない', async ({ page }) => {
  await loginAsSU(page);

  const nav = page.getByTestId('nav');
  await expect(nav.getByRole('link', { name: '全体ビュー' })).toBeVisible();
  await expect(nav.getByRole('link', { name: '企業', exact: true })).toBeVisible();
  await expect(nav.getByRole('link', { name: 'ユーザー', exact: true })).toBeVisible();
  await expect(nav.getByRole('link', { name: 'アンケート', exact: true })).toBeVisible();
  await expect(nav.getByRole('link', { name: 'ダッシュボード' })).toHaveCount(0);
  await expect(nav.getByRole('link', { name: 'CSV 登録' })).toHaveCount(0);
  await expect(nav.getByRole('link', { name: '部署' })).toHaveCount(0);
});

test('E2E-160-dsp ナビメニュー(管理者) 企業・全体ビューは表示されない', async ({ page }) => {
  await setupAdmin(page, 'e2e160');

  const nav = page.getByTestId('nav');
  await expect(nav.getByRole('link', { name: 'ダッシュボード' })).toBeVisible();
  await expect(nav.getByRole('link', { name: 'アンケート', exact: true })).toBeVisible();
  await expect(nav.getByRole('link', { name: 'ユーザー', exact: true })).toBeVisible();
  await expect(nav.getByRole('link', { name: 'CSV 登録' })).toBeVisible();
  await expect(nav.getByRole('link', { name: '部署' })).toBeVisible();
  await expect(nav.getByRole('link', { name: '企業', exact: true })).toHaveCount(0);
  await expect(nav.getByRole('link', { name: '全体ビュー' })).toHaveCount(0);
});

test('E2E-161-dsp ナビメニュー(ユーザー) アンケート一覧のみ表示される', async ({ page }) => {
  await setupUser(page, 'e2e161');

  const nav = page.getByTestId('nav');
  await expect(nav.getByRole('link', { name: 'アンケート一覧' })).toBeVisible();
  await expect(nav.getByRole('link', { name: 'ダッシュボード' })).toHaveCount(0);
  await expect(nav.getByRole('link', { name: 'ユーザー', exact: true })).toHaveCount(0);
  await expect(nav.getByRole('link', { name: 'CSV 登録' })).toHaveCount(0);
  await expect(nav.getByRole('link', { name: '部署' })).toHaveCount(0);
  await expect(nav.getByRole('link', { name: '企業', exact: true })).toHaveCount(0);
});

test('E2E-167-auth ユーザーの全体ビューアクセスは 403', async ({ page }) => {
  await setupUser(page, 'e2e167');
  await expectStatus(page, '/admin/home', 403);
});

test('E2E-168-auth ユーザーの CSV 画面アクセスは 403', async ({ page }) => {
  await setupUser(page, 'e2e168');
  await expectStatus(page, '/users/import', 403);
});

test('E2E-186-auth ユーザーのユーザー編集アクセスは 403', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e186');
  const otherEmail = `other-${fixture.companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: '別ユーザー', email: otherEmail });
  const selfEmail = `self-${fixture.companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: '操作者', email: selfEmail });
  const editHref = await page
    .locator('tbody tr', { hasText: otherEmail })
    .getByRole('link', { name: '編集' })
    .getAttribute('href');
  await logout(page);
  await login(page, fixture.companyCode, selfEmail, PASSWORD);

  await expectStatus(page, editHref ?? '', 403);
});

test('E2E-187-auth ユーザーのユーザー削除実行は 403 で削除されない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e187');
  const otherEmail = `other-${fixture.companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: '削除対象外', email: otherEmail });
  const selfEmail = `self-${fixture.companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: '操作者', email: selfEmail });
  const editHref = await page
    .locator('tbody tr', { hasText: otherEmail })
    .getByRole('link', { name: '編集' })
    .getAttribute('href');
  const userId = editHref?.match(/\/users\/(\d+)\/edit/)?.[1];
  await logout(page);
  await login(page, fixture.companyCode, selfEmail, PASSWORD);

  const status = await sendForm(page, 'DELETE', `/users/${userId}`);

  expect(status).toBe(403);
  await logout(page);
  await login(page, fixture.companyCode, fixture.adminEmail, PASSWORD);
  await page.goto('/users');
  await expect(page.locator('tbody tr', { hasText: otherEmail })).toBeVisible();
});

test('E2E-188-auth ユーザーのアンケート編集アクセスは 403', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e188');
  const userEmail = `self-${fixture.companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: '操作者', email: userEmail });
  const title = `編集不可${fixture.companyCode}`;
  await createSurvey(page, { title, action: 'draft' });
  const editHref = await page
    .locator('tbody tr', { hasText: title })
    .getByRole('link', { name: '編集' })
    .getAttribute('href');
  await logout(page);
  await login(page, fixture.companyCode, userEmail, PASSWORD);

  await expectStatus(page, editHref ?? '', 403);
});

test('E2E-189-auth ユーザーのアンケート公開実行は 403 で下書きのまま', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e189');
  const userEmail = `self-${fixture.companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: '操作者', email: userEmail });
  const title = `公開不可${fixture.companyCode}`;
  await createSurvey(page, { title, action: 'draft' });
  const editHref = await page
    .locator('tbody tr', { hasText: title })
    .getByRole('link', { name: '編集' })
    .getAttribute('href');
  const surveyId = editHref?.match(/\/surveys\/(\d+)\/edit/)?.[1];
  await logout(page);
  await login(page, fixture.companyCode, userEmail, PASSWORD);

  const status = await sendForm(page, 'POST', `/surveys/${surveyId}/publish`);

  expect(status).toBe(403);
  await logout(page);
  await login(page, fixture.companyCode, fixture.adminEmail, PASSWORD);
  await page.goto('/surveys');
  await expect(page.locator('tbody tr', { hasText: title })).toContainText('下書き');
});

test('E2E-190-auth ユーザーのアンケート削除実行は 403 で一覧に残る', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e190');
  const userEmail = `self-${fixture.companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: '操作者', email: userEmail });
  const title = `削除不可${fixture.companyCode}`;
  await createSurvey(page, { title });
  const editHref = await page
    .locator('tbody tr', { hasText: title })
    .getByRole('link', { name: '編集' })
    .getAttribute('href');
  const surveyId = editHref?.match(/\/surveys\/(\d+)\/edit/)?.[1];
  await logout(page);
  await login(page, fixture.companyCode, userEmail, PASSWORD);

  const status = await sendForm(page, 'DELETE', `/surveys/${surveyId}`);

  expect(status).toBe(403);
  await logout(page);
  await login(page, fixture.companyCode, fixture.adminEmail, PASSWORD);
  await page.goto('/surveys');
  await expect(page.locator('tbody tr', { hasText: title })).toBeVisible();
});

test('E2E-191-auth SU 全体ビューの部署画面は 403', async ({ page }) => {
  await loginAsSU(page);
  await expectStatus(page, '/departments', 403);
});
