// アンケート回答（E2E-117〜E2E-128）— 出典: docs/specs/survey-system/03-test-plan.csv
import { test, expect, Page } from '@playwright/test';
import {
  PASSWORD,
  answerSurvey,
  chartValues,
  createSurvey,
  login,
  logout,
  registerUser,
  setupAdmin,
  SurveyInput,
} from './helpers';

/** 管理者で企業・ユーザー・アンケートを作成し、ユーザーでログインした状態にする */
async function setupAnswerable(page: Page, testId: string, survey: Partial<SurveyInput> = {}) {
  const fixture = await setupAdmin(page, testId);
  const userEmail = `user-${fixture.companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: `回答者${fixture.companyCode}`, email: userEmail });
  const title = `回答用${fixture.companyCode}`;
  await createSurvey(page, { title, question: { required: true }, ...survey });
  await logout(page);
  await login(page, fixture.companyCode, userEmail, PASSWORD);
  return { ...fixture, userEmail, title };
}

test('E2E-117-evt アンケート回答(初回) 送信後に回答済セクションに移動する', async ({ page }) => {
  const { title } = await setupAnswerable(page, 'e2e117');

  await answerSurvey(page, title, '満足');

  await expect(page.getByTestId('answered-list').getByText(title)).toBeVisible();
  await expect(page.getByTestId('unanswered-list')).toHaveCount(0);
});

test('E2E-118-evt アンケート回答(修正) 再表示で新しい選択が保持されダッシュボード集計に反映される', async ({ page }) => {
  const fixture = await setupAnswerable(page, 'e2e118');
  await answerSurvey(page, fixture.title, '満足');

  await page.getByRole('link', { name: fixture.title }).click();
  await page.getByRole('radio', { name: '普通' }).check();
  await page.getByRole('button', { name: '回答を修正する' }).click();
  await expect(page.getByTestId('flash-success')).toContainText('回答を送信しました');

  await page.getByRole('link', { name: fixture.title }).click();
  await expect(page.getByRole('radio', { name: '普通' })).toBeChecked();
  // 管理者のダッシュボードで普通=1 になっている
  await logout(page);
  await login(page, fixture.companyCode, fixture.adminEmail, PASSWORD);
  await page.goto('/dashboard');
  await expect(page.getByTestId('total-responses')).toHaveText('1 件');
  expect(await chartValues(page)).toEqual({ 満足: 0, 普通: 1, 不満: 0 });
});

test('E2E-120-inp 回答(必須設問未回答) 設問下にエラーが表示され未回答のまま', async ({ page }) => {
  const { title } = await setupAnswerable(page, 'e2e120');

  await page.getByRole('link', { name: title }).click();
  await page.getByRole('button', { name: '回答を送信する' }).click();

  await expect(page.locator('[data-testid^="question-error-"]')).toHaveText('この設問は必須です');
  await page.goto('/my/surveys');
  await expect(page.getByTestId('unanswered-list').getByText(title)).toBeVisible();
});

test('E2E-126-dsp 終了アンケート(未回答ユーザー) 受付終了メッセージが表示される', async ({ page }) => {
  const fixture = await setupAnswerable(page, 'e2e126');
  // 未回答のまま管理者が終了に切り替える
  await logout(page);
  await login(page, fixture.companyCode, fixture.adminEmail, PASSWORD);
  await page.goto('/surveys');
  await page.locator('tbody tr', { hasText: fixture.title }).getByRole('button', { name: '終了' }).click();
  await logout(page);
  await login(page, fixture.companyCode, fixture.userEmail, PASSWORD);

  await page.getByRole('link', { name: fixture.title }).click();

  await expect(page.getByTestId('closed-message')).toContainText('回答受付は終了しました');
  await expect(page.getByRole('radio')).toHaveCount(0);
});

test('E2E-128-auth 下書きアンケートの直接アクセスは 404 になる', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e128');
  const userEmail = `user-${fixture.companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: '回答者', email: userEmail });
  const title = `下書き直接${fixture.companyCode}`;
  await createSurvey(page, { title, action: 'draft' });
  const row = page.locator('tbody tr', { hasText: title });
  const editHref = await row.getByRole('link', { name: '編集' }).getAttribute('href');
  const surveyId = editHref?.match(/\/surveys\/(\d+)\/edit/)?.[1];
  await logout(page);
  await login(page, fixture.companyCode, userEmail, PASSWORD);

  const response = await page.goto(`/my/surveys/${surveyId}`);

  expect(response?.status()).toBe(404);
});
