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

test('E2E-085-evt アンケート公開作成はユーザーの未回答一覧に表示される', async ({ page }) => {
  const fixture = await setupAdminWithUser(page, 'e2e085');
  const title = `公開${fixture.companyCode}`;

  await createSurvey(page, { title, action: 'publish' });

  await expect(page.locator('tbody tr', { hasText: title })).toContainText('公開');
  await logout(page);
  await login(page, fixture.companyCode, fixture.userEmail, PASSWORD);
  await expect(page.getByTestId('unanswered-list').getByText(title)).toBeVisible();
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

test('E2E-089-evt 締切日時到達で自動終了しユーザーは回答できない', async ({ page }) => {
  test.setTimeout(240_000);
  const fixture = await setupAdminWithUser(page, 'e2e089');
  const title = `締切${fixture.companyCode}`;
  // datetime-local は分単位のため締切は現在+1 分（サーバー TZ = UTC）で公開する
  await createSurvey(page, { title, deadlineAt: utcDatetimeLocal(1) });
  await logout(page);
  await login(page, fixture.companyCode, fixture.userEmail, PASSWORD);
  await page.getByRole('link', { name: title }).click();

  // 固定待機ではなく表示変化をポーリングで待つ（上限 90 秒）
  await expect(async () => {
    await page.reload();
    await expect(page.getByTestId('closed-message')).toBeVisible({ timeout: 1_000 });
  }).toPass({ timeout: 90_000, intervals: [5_000] });

  await expect(page.getByTestId('closed-message')).toContainText('回答受付は終了しました');
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

test('E2E-092-inp アンケート(タイトル101文字) 文字数エラーで作成されない', async ({ page }) => {
  await setupAdmin(page, 'e2e092');
  await fillMinimumSurvey(page, 'あ'.repeat(101));

  await page.getByRole('button', { name: '公開', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('タイトルは 100 文字以内で入力してください');
});

test('E2E-093-inp アンケート(タイトル100文字境界) 作成成功する', async ({ page }) => {
  await setupAdmin(page, 'e2e093');
  const title = 'あ'.repeat(100);
  await fillMinimumSurvey(page, title);

  await page.getByRole('button', { name: '公開', exact: true }).click();

  await expect(page).toHaveURL(/\/surveys$/);
  await expect(page.locator('tbody tr', { hasText: title })).toBeVisible();
});

test('E2E-094-inp アンケート(説明1001文字) 文字数エラーで作成されない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e094');
  await fillMinimumSurvey(page, `説明超過${fixture.companyCode}`);
  await page.fill('#description', 'あ'.repeat(1001));

  await page.getByRole('button', { name: '公開', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('説明は 1000 文字以内で入力してください');
});

test('E2E-095-inp アンケート(設問0問) エラーで作成されない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e095');
  await page.goto('/surveys/create');
  await page.fill('#title', `設問なし${fixture.companyCode}`);
  await page.getByTestId('remove-question-0').click();

  await page.getByRole('button', { name: '公開', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('設問を 1 問以上追加してください');
});

test('E2E-096-inp アンケート(設問文未入力) 必須エラーで作成されない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e096');
  await page.goto('/surveys/create');
  await page.fill('#title', `設問文なし${fixture.companyCode}`);
  await page.fill('input[name="questions[0][options][0]"]', '満足');
  await page.fill('input[name="questions[0][options][1]"]', '普通');

  await page.getByRole('button', { name: '公開', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('設問文は必須です');
});

test('E2E-097-inp アンケート(設問文501文字) 文字数エラーで作成されない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e097');
  await fillMinimumSurvey(page, `設問文超過${fixture.companyCode}`);
  await page.fill('textarea[name="questions[0][body]"]', 'あ'.repeat(501));

  await page.getByRole('button', { name: '公開', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('設問文は 500 文字以内で入力してください');
});

test('E2E-098-inp アンケート(設問51問) エラーで作成されない', async ({ page }) => {
  test.setTimeout(120_000);
  const fixture = await setupAdmin(page, 'e2e098');
  await page.goto('/surveys/create');
  await page.fill('#title', `設問超過${fixture.companyCode}`);
  for (let i = 1; i < 51; i += 1) {
    await page.getByRole('button', { name: '設問を追加' }).click();
  }
  for (let i = 0; i < 51; i += 1) {
    await page.fill(`textarea[name="questions[${i}][body]"]`, `設問${i + 1}`);
    await page.fill(`input[name="questions[${i}][options][0]"]`, 'はい');
    await page.fill(`input[name="questions[${i}][options][1]"]`, 'いいえ');
  }

  await page.getByRole('button', { name: '公開', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('設問は 50 問以内にしてください');
});

test('E2E-099-inp アンケート(設問50問境界) 作成成功し回答画面に 50 問表示される', async ({ page }) => {
  test.setTimeout(120_000);
  const fixture = await setupAdminWithUser(page, 'e2e099');
  const title = `設問50${fixture.companyCode}`;
  await page.goto('/surveys/create');
  await page.fill('#title', title);
  for (let i = 1; i < 50; i += 1) {
    await page.getByRole('button', { name: '設問を追加' }).click();
  }
  for (let i = 0; i < 50; i += 1) {
    await page.fill(`textarea[name="questions[${i}][body]"]`, `設問${i + 1}`);
    await page.fill(`input[name="questions[${i}][options][0]"]`, 'はい');
    await page.fill(`input[name="questions[${i}][options][1]"]`, 'いいえ');
  }
  await page.getByRole('button', { name: '公開', exact: true }).click();
  await expect(page).toHaveURL(/\/surveys$/);

  await logout(page);
  await login(page, fixture.companyCode, fixture.userEmail, PASSWORD);
  await page.getByRole('link', { name: title }).click();
  await expect(page.getByText('設問 50.')).toBeVisible();
});

test('E2E-100-inp アンケート(選択肢1個) 個数エラーで作成されない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e100');
  await fillMinimumSurvey(page, `選択肢1${fixture.companyCode}`);
  await page.getByTestId('remove-option-0-1').click();

  await page.getByRole('button', { name: '公開', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('選択肢は 2 個以上 10 個以内で入力してください');
});

test('E2E-101-inp アンケート(選択肢11個) 個数エラーで作成されない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e101');
  await fillMinimumSurvey(page, `選択肢11${fixture.companyCode}`);
  for (let i = 2; i < 11; i += 1) {
    await page.getByTestId('add-option-0').click();
    await page.fill(`input[name="questions[0][options][${i}]"]`, `選択肢${i + 1}`);
  }
  await page.getByTestId('add-option-0').click();
  await page.fill('input[name="questions[0][options][10]"]', '選択肢11');

  await page.getByRole('button', { name: '公開', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('選択肢は 2 個以上 10 個以内で入力してください');
});

test('E2E-102-inp アンケート(選択肢2個境界) 作成成功する', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e102');
  const title = `選択肢2${fixture.companyCode}`;
  await fillMinimumSurvey(page, title);

  await page.getByRole('button', { name: '公開', exact: true }).click();

  await expect(page).toHaveURL(/\/surveys$/);
  await expect(page.locator('tbody tr', { hasText: title })).toBeVisible();
});

test('E2E-103-inp アンケート(選択肢10個境界) 作成成功し回答画面に 10 個表示される', async ({ page }) => {
  const fixture = await setupAdminWithUser(page, 'e2e103');
  const title = `選択肢10${fixture.companyCode}`;
  await fillMinimumSurvey(page, title);
  for (let i = 2; i < 10; i += 1) {
    await page.getByTestId('add-option-0').click();
    await page.fill(`input[name="questions[0][options][${i}]"]`, `選択肢${i + 1}`);
  }
  await page.getByRole('button', { name: '公開', exact: true }).click();
  await expect(page).toHaveURL(/\/surveys$/);

  await logout(page);
  await login(page, fixture.companyCode, fixture.userEmail, PASSWORD);
  await page.getByRole('link', { name: title }).click();
  await expect(page.getByRole('radio')).toHaveCount(10);
});

test('E2E-104-inp アンケート(選択肢ラベル空) 必須エラーで作成されない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e104');
  await fillMinimumSurvey(page, `ラベル空${fixture.companyCode}`);
  await page.fill('input[name="questions[0][options][1]"]', '');

  await page.getByRole('button', { name: '公開', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('選択肢は必須です');
});

test('E2E-105-inp アンケート(選択肢101文字) 文字数エラーで作成されない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e105');
  await fillMinimumSurvey(page, `選択肢超過${fixture.companyCode}`);
  await page.fill('input[name="questions[0][options][1]"]', 'あ'.repeat(101));

  await page.getByRole('button', { name: '公開', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('選択肢は 100 文字以内で入力してください');
});

test('E2E-106-inp アンケート(過去締切で公開) エラーで公開されない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e106');
  await fillMinimumSurvey(page, `過去締切${fixture.companyCode}`);
  await page.fill('#deadline_at', '2020-01-01T00:00');

  await page.getByRole('button', { name: '公開', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('締切日時は未来の日時を指定してください');
});

test('E2E-107-inp アンケート(未来締切で公開) 公開成功し一覧に締切が表示される', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e107');
  const title = `未来締切${fixture.companyCode}`;
  const nextYearEnd = `${new Date().getFullYear() + 1}-12-31T23:59`;
  await fillMinimumSurvey(page, title);
  await page.fill('#deadline_at', nextYearEnd);

  await page.getByRole('button', { name: '公開', exact: true }).click();

  const row = page.locator('tbody tr', { hasText: title });
  await expect(row).toContainText('公開');
  await expect(row).toContainText(`${new Date().getFullYear() + 1}-12-31 23:59`);
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

test('E2E-109-evt 公開後のタイトル・説明・締切編集が一覧に反映される', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e109');
  const title = `編集前${fixture.companyCode}`;
  const newTitle = `編集後${fixture.companyCode}`;
  await createSurvey(page, { title });

  await page.locator('tbody tr', { hasText: title }).getByRole('link', { name: '編集' }).click();
  await page.fill('#title', newTitle);
  await page.fill('#description', '説明を変更しました');
  const nextYearEnd = `${new Date().getFullYear() + 1}-06-30T12:00`;
  await page.fill('#deadline_at', nextYearEnd);
  await page.getByRole('button', { name: '保存' }).click();

  await expect(page).toHaveURL(/\/surveys$/);
  const row = page.locator('tbody tr', { hasText: newTitle });
  await expect(row).toBeVisible();
  await expect(row).toContainText(`${new Date().getFullYear() + 1}-06-30 12:00`);
});

test('E2E-110-dsp 管理一覧にタイトル・状態・締切・回答数・作成日が表示される', async ({ page }) => {
  const fixture = await setupAdminWithUser(page, 'e2e110');
  const title = `一覧表示${fixture.companyCode}`;
  const nextYearEnd = `${new Date().getFullYear() + 1}-12-31T23:59`;
  await createSurvey(page, { title, deadlineAt: nextYearEnd });
  await logout(page);
  await login(page, fixture.companyCode, fixture.userEmail, PASSWORD);
  await answerSurvey(page, title, '満足');
  await logout(page);
  await login(page, fixture.companyCode, fixture.adminEmail, PASSWORD);

  await page.goto('/surveys');

  const row = page.locator('tbody tr', { hasText: title });
  await expect(row).toContainText('公開');
  await expect(row).toContainText(`${new Date().getFullYear() + 1}-12-31 23:59`);
  await expect(row).toContainText('1');
  const today = new Date().toISOString().slice(0, 10);
  await expect(row).toContainText(today);
});

test('E2E-111-dsp 一覧の下書き行には公開ボタンがあり終了ボタンはない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e111');
  const title = `下書き行${fixture.companyCode}`;
  await createSurvey(page, { title, action: 'draft' });

  const row = page.locator('tbody tr', { hasText: title });
  await expect(row.getByRole('button', { name: '公開' })).toBeVisible();
  await expect(row.getByRole('button', { name: '終了' })).toHaveCount(0);
  await expect(row.getByRole('link', { name: '編集' })).toBeVisible();
  await expect(row.getByRole('button', { name: '削除' })).toBeVisible();
});

test('E2E-112-dsp 一覧の公開行には終了ボタンがあり公開ボタンはない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e112');
  const title = `公開行${fixture.companyCode}`;
  await createSurvey(page, { title });

  const row = page.locator('tbody tr', { hasText: title });
  await expect(row.getByRole('button', { name: '終了' })).toBeVisible();
  await expect(row.getByRole('button', { name: '公開' })).toHaveCount(0);
});

test('E2E-113-evt 削除確認ダイアログでキャンセルすると削除されない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e113');
  const title = `キャンセル${fixture.companyCode}`;
  await createSurvey(page, { title });
  let dialogShown = false;
  page.on('dialog', (dialog) => {
    dialogShown = true;
    dialog.dismiss();
  });

  await page.locator('tbody tr', { hasText: title }).getByRole('button', { name: '削除' }).click();

  expect(dialogShown).toBe(true);
  await expect(page.locator('tbody tr', { hasText: title })).toBeVisible();
});

test('E2E-114-dyn 設問の動的追加・削除で番号が振り直される', async ({ page }) => {
  await setupAdmin(page, 'e2e114');
  await page.goto('/surveys/create');

  await page.getByRole('button', { name: '設問を追加' }).click();
  await page.getByRole('button', { name: '設問を追加' }).click();
  await expect(page.locator('[data-testid^="question-"]:not([data-testid*="option"])')).toHaveCount(3);

  await page.getByTestId('remove-question-1').click();

  await expect(page.locator('[data-testid^="question-"]:not([data-testid*="option"])')).toHaveCount(2);
  await expect(page.getByText('設問 1', { exact: true })).toBeVisible();
  await expect(page.getByText('設問 2', { exact: true })).toBeVisible();
  await expect(page.getByText('設問 3', { exact: true })).toHaveCount(0);
});

test('E2E-115-dyn 選択肢の動的追加・削除ができる', async ({ page }) => {
  await setupAdmin(page, 'e2e115');
  await page.goto('/surveys/create');

  await page.getByTestId('add-option-0').click();
  await page.getByTestId('add-option-0').click();
  await expect(page.locator('input[name^="questions[0][options]"]')).toHaveCount(4);

  await page.getByTestId('remove-option-0-3').click();

  await expect(page.locator('input[name^="questions[0][options]"]')).toHaveCount(3);
});

test('E2E-116-dyn 設問形式の変更で選択肢欄の表示が切り替わる', async ({ page }) => {
  await setupAdmin(page, 'e2e116');
  await page.goto('/surveys/create');
  await expect(page.getByTestId('options-0')).toBeVisible();

  await page.selectOption('select[name="questions[0][type]"]', 'text');
  await expect(page.getByTestId('options-0')).toHaveCount(0);

  await page.selectOption('select[name="questions[0][type]"]', 'multiple');
  await expect(page.getByTestId('options-0')).toBeVisible();
});

test('E2E-166-auth SU は企業ビューで他社アンケートを終了できる', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e166');
  const title = `SU終了${fixture.companyCode}`;
  await createSurvey(page, { title });
  await logout(page);
  await loginAsSU(page);
  await switchToCompany(page, fixture.companyName);
  await page.goto('/surveys');

  await page.locator('tbody tr', { hasText: title }).getByRole('button', { name: '終了' }).click();

  await expect(page.locator('tbody tr', { hasText: title })).toContainText('終了');
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

test('E2E-171-inp アンケート編集(説明空上書き) 再表示で説明が空になる', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e171');
  const title = `説明クリア${fixture.companyCode}`;
  await createSurvey(page, { title, description: '元の説明', action: 'draft' });

  await page.locator('tbody tr', { hasText: title }).getByRole('link', { name: '編集' }).click();
  await page.fill('#description', '');
  await page.getByRole('button', { name: '下書き保存' }).click();
  await expect(page).toHaveURL(/\/surveys$/);
  await page.locator('tbody tr', { hasText: title }).getByRole('link', { name: '編集' }).click();

  await expect(page.locator('#description')).toHaveValue('');
});

test('E2E-172-inp アンケート編集(締切空上書き) 再表示と一覧の締切が空になる', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e172');
  const title = `締切クリア${fixture.companyCode}`;
  const nextYearEnd = `${new Date().getFullYear() + 1}-12-31T23:59`;
  await createSurvey(page, { title, deadlineAt: nextYearEnd, action: 'draft' });

  await page.locator('tbody tr', { hasText: title }).getByRole('link', { name: '編集' }).click();
  await page.fill('#deadline_at', '');
  await page.getByRole('button', { name: '下書き保存' }).click();
  await expect(page).toHaveURL(/\/surveys$/);

  await expect(page.locator('tbody tr', { hasText: title })).not.toContainText('12-31');
  await page.locator('tbody tr', { hasText: title }).getByRole('link', { name: '編集' }).click();
  await expect(page.locator('#deadline_at')).toHaveValue('');
});

test('E2E-173-inp アンケート(説明1000文字境界) 作成成功する', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e173');
  const title = `説明境界${fixture.companyCode}`;
  await fillMinimumSurvey(page, title);
  await page.fill('#description', 'あ'.repeat(1000));

  await page.getByRole('button', { name: '公開', exact: true }).click();

  await expect(page).toHaveURL(/\/surveys$/);
  await expect(page.locator('tbody tr', { hasText: title })).toBeVisible();
});

test('E2E-174-inp アンケート(設問文500文字境界) 作成成功し回答画面に表示される', async ({ page }) => {
  const fixture = await setupAdminWithUser(page, 'e2e174');
  const title = `設問文境界${fixture.companyCode}`;
  const body = 'あ'.repeat(500);
  await fillMinimumSurvey(page, title);
  await page.fill('textarea[name="questions[0][body]"]', body);
  await page.getByRole('button', { name: '公開', exact: true }).click();
  await expect(page).toHaveURL(/\/surveys$/);

  await logout(page);
  await login(page, fixture.companyCode, fixture.userEmail, PASSWORD);
  await page.getByRole('link', { name: title }).click();
  await expect(page.getByText(body)).toBeVisible();
});

test('E2E-175-inp アンケート(選択肢100文字境界) 作成成功する', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e175');
  const title = `選択肢境界${fixture.companyCode}`;
  await fillMinimumSurvey(page, title);
  await page.fill('input[name="questions[0][options][1]"]', 'あ'.repeat(100));

  await page.getByRole('button', { name: '公開', exact: true }).click();

  await expect(page).toHaveURL(/\/surveys$/);
  await expect(page.locator('tbody tr', { hasText: title })).toBeVisible();
});

test('E2E-192-evt 一覧からの公開実行でユーザーの未回答一覧に表示される', async ({ page }) => {
  const fixture = await setupAdminWithUser(page, 'e2e192');
  const title = `一覧公開${fixture.companyCode}`;
  await createSurvey(page, { title, action: 'draft' });

  await page.locator('tbody tr', { hasText: title }).getByRole('button', { name: '公開' }).click();

  await expect(page.locator('tbody tr', { hasText: title })).toContainText('公開');
  await logout(page);
  await login(page, fixture.companyCode, fixture.userEmail, PASSWORD);
  await expect(page.getByTestId('unanswered-list').getByText(title)).toBeVisible();
});
