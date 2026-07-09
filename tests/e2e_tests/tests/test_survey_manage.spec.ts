// アンケート管理（E2E-084〜E2E-116, E2E-166, E2E-170〜E2E-175, E2E-192）
// 出典: docs/specs/survey-system/03-test-plan.csv
import { test, expect, Page } from '@playwright/test';
import {
  PASSWORD,
  answerSurvey,
  createSurvey,
  login,
  loginAsSU,
  logout,
  registerUser,
  setupAdmin,
  setupCompany,
  switchToCompany,
  uid,
  utcDatetimeLocal,
} from './helpers';

/** 管理者ログイン状態のまま、同企業のユーザーも用意する */
async function setupAdminWithUser(page: Page, testId: string) {
  const fixture = await setupAdmin(page, testId);
  const userEmail = `user-${fixture.companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: `回答者${fixture.companyCode}`, email: userEmail });
  return { ...fixture, userEmail };
}

/** 作成画面を開いてタイトルと設問 1 問（選択肢 満足/普通）だけ埋める */
async function fillMinimumSurvey(page: Page, title: string): Promise<void> {
  await page.goto('/surveys/create');
  await page.fill('#title', title);
  await page.fill('textarea[name="questions[0][body]"]', '満足度を教えてください');
  await page.fill('input[name="questions[0][options][0]"]', '満足');
  await page.fill('input[name="questions[0][options][1]"]', '普通');
}

test('E2E-084-evt アンケート下書き保存はユーザー一覧に表示されない', async ({ page }) => {
  const fixture = await setupAdminWithUser(page, 'e2e084');
  const title = `下書き${fixture.companyCode}`;

  await createSurvey(page, { title, action: 'draft' });

  const row = page.locator('tbody tr', { hasText: title });
  await expect(row).toContainText('下書き');
  await logout(page);
  await login(page, fixture.companyCode, fixture.userEmail, PASSWORD);
  await expect(page.getByText(title)).toHaveCount(0);
});

test('E2E-086-evt SU 代行アンケート作成は対象企業ユーザーの未回答一覧に表示される', async ({ page }) => {
  const fixture = await setupCompany(page, 'e2e086');
  const userEmail = `user-${fixture.companyCode}@example.com`;
  await registerUser(page, {
    companyName: fixture.companyName,
    role: 'user',
    name: '代行対象ユーザー',
    email: userEmail,
  });
  const title = `代行${fixture.companyCode}`;

  await createSurvey(page, { title, companyName: fixture.companyName, action: 'publish' });

  await logout(page);
  await login(page, fixture.companyCode, userEmail, PASSWORD);
  await expect(page.getByTestId('unanswered-list').getByText(title)).toBeVisible();
});

test('E2E-087-evt 下書き編集して公開すると編集後の設問文が表示される', async ({ page }) => {
  const fixture = await setupAdminWithUser(page, 'e2e087');
  const title = `編集公開${fixture.companyCode}`;
  await createSurvey(page, { title, question: { body: '旧設問文' }, action: 'draft' });

  await page.locator('tbody tr', { hasText: title }).getByRole('link', { name: '編集' }).click();
  await page.fill('textarea[name="questions[0][body]"]', '新設問文');
  await page.getByRole('button', { name: '公開', exact: true }).click();
  await expect(page).toHaveURL(/\/surveys$/);

  await logout(page);
  await login(page, fixture.companyCode, fixture.userEmail, PASSWORD);
  await page.getByRole('link', { name: title }).click();
  await expect(page.getByText('新設問文')).toBeVisible();
});

test('E2E-088-evt 公開中を終了に切替えるとユーザー側は読み取り専用になる', async ({ page }) => {
  const fixture = await setupAdminWithUser(page, 'e2e088');
  const title = `終了切替${fixture.companyCode}`;
  await createSurvey(page, { title });
  // ユーザーが回答してから終了に切り替える
  await logout(page);
  await login(page, fixture.companyCode, fixture.userEmail, PASSWORD);
  await answerSurvey(page, title, '満足');
  await logout(page);
  await login(page, fixture.companyCode, fixture.adminEmail, PASSWORD);
  await page.goto('/surveys');

  await page.locator('tbody tr', { hasText: title }).getByRole('button', { name: '終了' }).click();

  await expect(page.locator('tbody tr', { hasText: title })).toContainText('終了');
  await logout(page);
  await login(page, fixture.companyCode, fixture.userEmail, PASSWORD);
  await page.getByRole('link', { name: title }).click();
  await expect(page.getByRole('button', { name: /回答を/ })).toHaveCount(0);
});

test('E2E-090-evt アンケート削除でユーザー一覧とダッシュボード選択肢から消える', async ({ page }) => {
  const fixture = await setupAdminWithUser(page, 'e2e090');
  const title = `削除対象${fixture.companyCode}`;
  await createSurvey(page, { title });
  page.on('dialog', (dialog) => dialog.accept());

  await page.locator('tbody tr', { hasText: title }).getByRole('button', { name: '削除' }).click();

  await expect(page.locator('tbody tr', { hasText: title })).toHaveCount(0);
  await page.goto('/dashboard');
  await expect(page.locator('#survey-select option', { hasText: title })).toHaveCount(0);
  await logout(page);
  await login(page, fixture.companyCode, fixture.userEmail, PASSWORD);
  await expect(page.getByText(title)).toHaveCount(0);
});

test('E2E-091-inp アンケート(タイトル未入力) 必須エラーで作成されない', async ({ page }) => {
  await setupAdmin(page, 'e2e091');
  await fillMinimumSurvey(page, '');

  await page.getByRole('button', { name: '公開', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('タイトルは必須です');
  await page.goto('/surveys');
  await expect(page.locator('tbody tr')).toHaveCount(0);
});

test('E2E-108-dsp 公開後編集画面は設問が読み取り専用でタイトル等のみ入力可能', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e108');
  const title = `公開後編集${fixture.companyCode}`;
  await createSurvey(page, { title });

  await page.locator('tbody tr', { hasText: title }).getByRole('link', { name: '編集' }).click();

  await expect(page.getByText('設問（公開後は変更できません）')).toBeVisible();
  const readonlyQuestion = page.getByTestId('readonly-question-0');
  await expect(readonlyQuestion.locator('input').first()).toBeDisabled();
  await expect(page.locator('#title')).toBeEnabled();
  await expect(page.locator('#description')).toBeEnabled();
  await expect(page.locator('#deadline_at')).toBeEnabled();
});

test('E2E-170-inp アンケート編集(タイトル空更新) 必須エラーでタイトルは変わらない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e170');
  const title = `タイトルT${fixture.companyCode}`;
  await createSurvey(page, { title, action: 'draft' });

  await page.locator('tbody tr', { hasText: title }).getByRole('link', { name: '編集' }).click();
  await page.fill('#title', '');
  await page.getByRole('button', { name: '下書き保存' }).click();

  await expect(page.getByTestId('form-errors')).toContainText('タイトルは必須です');
  await page.goto('/surveys');
  await expect(page.locator('tbody tr', { hasText: title })).toBeVisible();
});
