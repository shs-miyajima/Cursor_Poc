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

test('E2E-119-dsp 回答済アンケートは前回の回答が初期表示されボタンが修正表記になる', async ({ page }) => {
  const { title } = await setupAnswerable(page, 'e2e119');
  await answerSurvey(page, title, '満足');

  await page.getByRole('link', { name: title }).click();

  await expect(page.getByRole('radio', { name: '満足' })).toBeChecked();
  await expect(page.getByRole('button', { name: '回答を修正する' })).toBeVisible();
});

test('E2E-120-inp 回答(必須設問未回答) 設問下にエラーが表示され未回答のまま', async ({ page }) => {
  const { title } = await setupAnswerable(page, 'e2e120');

  await page.getByRole('link', { name: title }).click();
  await page.getByRole('button', { name: '回答を送信する' }).click();

  await expect(page.locator('[data-testid^="question-error-"]')).toHaveText('この設問は必須です');
  await page.goto('/my/surveys');
  await expect(page.getByTestId('unanswered-list').getByText(title)).toBeVisible();
});

test('E2E-121-inp 回答(任意設問未回答) 送信成功し回答済一覧に移動する', async ({ page }) => {
  const { title } = await setupAnswerable(page, 'e2e121', {
    question: { type: 'text', required: false },
  });

  await page.getByRole('link', { name: title }).click();
  await page.getByRole('button', { name: '回答を送信する' }).click();

  await expect(page.getByTestId('flash-success')).toContainText('回答を送信しました');
  await expect(page.getByTestId('answered-list').getByText(title)).toBeVisible();
});

test('E2E-122-inp 回答(自由記述1001文字) 文字数エラーで保存されない', async ({ page }) => {
  const { title } = await setupAnswerable(page, 'e2e122', {
    question: { type: 'text', required: false },
  });

  await page.getByRole('link', { name: title }).click();
  await page.locator('textarea[name^="answers"]').fill('あ'.repeat(1001));
  await page.getByRole('button', { name: '回答を送信する' }).click();

  await expect(page.locator('[data-testid^="question-error-"]')).toHaveText('1000 文字以内で入力してください');
  await page.goto('/my/surveys');
  await expect(page.getByTestId('unanswered-list').getByText(title)).toBeVisible();
});

test('E2E-123-inp 回答(自由記述1000文字境界) 送信成功し再表示で保持される', async ({ page }) => {
  const { title } = await setupAnswerable(page, 'e2e123', {
    question: { type: 'text', required: false },
  });
  const text = 'あ'.repeat(1000);

  await page.getByRole('link', { name: title }).click();
  await page.locator('textarea[name^="answers"]').fill(text);
  await page.getByRole('button', { name: '回答を送信する' }).click();
  await expect(page.getByTestId('flash-success')).toContainText('回答を送信しました');

  await page.getByRole('link', { name: title }).click();
  await expect(page.locator('textarea[name^="answers"]')).toHaveValue(text);
});

test('E2E-124-evt 終了後の回答送信はエラーになり保存されない', async ({ browser }) => {
  const adminContext = await browser.newContext();
  const adminPage = await adminContext.newPage();
  const fixture = await setupAdmin(adminPage, 'e2e124');
  const userEmail = `user-${fixture.companyCode}@example.com`;
  await registerUser(adminPage, { role: 'user', name: '回答者', email: userEmail });
  const title = `終了競合${fixture.companyCode}`;
  await createSurvey(adminPage, { title });

  // ユーザーが回答画面を開いたままにする
  const userContext = await browser.newContext();
  const userPage = await userContext.newPage();
  await login(userPage, fixture.companyCode, userEmail, PASSWORD);
  await userPage.getByRole('link', { name: title }).click();
  await userPage.getByRole('radio', { name: '満足' }).check();

  // 管理者が終了に切り替えた後に送信する
  await adminPage.goto('/surveys');
  await adminPage.locator('tbody tr', { hasText: title }).getByRole('button', { name: '終了' }).click();
  await expect(adminPage.locator('tbody tr', { hasText: title })).toContainText('終了');

  await userPage.getByRole('button', { name: '回答を送信する' }).click();

  await expect(userPage.getByTestId('form-errors')).toContainText('このアンケートは回答を受け付けていません');
  await userPage.goto('/my/surveys');
  await expect(userPage.getByTestId('answered-list')).toHaveCount(0);

  await adminContext.close();
  await userContext.close();
});

test('E2E-125-dsp 終了アンケート(回答済ユーザー) 以前の回答が読み取り専用で表示される', async ({ page }) => {
  const fixture = await setupAnswerable(page, 'e2e125');
  await answerSurvey(page, fixture.title, '満足');
  // 管理者が終了に切り替える
  await logout(page);
  await login(page, fixture.companyCode, fixture.adminEmail, PASSWORD);
  await page.goto('/surveys');
  await page.locator('tbody tr', { hasText: fixture.title }).getByRole('button', { name: '終了' }).click();
  await logout(page);
  await login(page, fixture.companyCode, fixture.userEmail, PASSWORD);

  await page.getByRole('link', { name: fixture.title }).click();

  const checked = page.getByRole('radio', { name: '満足' });
  await expect(checked).toBeChecked();
  await expect(checked).toBeDisabled();
  await expect(page.getByRole('button', { name: /回答を/ })).toHaveCount(0);
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

test('E2E-127-evt 複数選択設問の回答は再表示で 2 個ともチェック済みになる', async ({ page }) => {
  const { title } = await setupAnswerable(page, 'e2e127', {
    question: { type: 'multiple', options: ['選択肢A', '選択肢B', '選択肢C'] },
  });

  await page.getByRole('link', { name: title }).click();
  await page.getByRole('checkbox', { name: '選択肢A' }).check();
  await page.getByRole('checkbox', { name: '選択肢C' }).check();
  await page.getByRole('button', { name: '回答を送信する' }).click();
  await expect(page.getByTestId('flash-success')).toContainText('回答を送信しました');

  await page.getByRole('link', { name: title }).click();
  await expect(page.getByRole('checkbox', { name: '選択肢A' })).toBeChecked();
  await expect(page.getByRole('checkbox', { name: '選択肢C' })).toBeChecked();
  await expect(page.getByRole('checkbox', { name: '選択肢B' })).not.toBeChecked();
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
