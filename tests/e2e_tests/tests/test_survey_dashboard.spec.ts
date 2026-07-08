// ダッシュボード（E2E-129〜E2E-144, E2E-183）— 出典: docs/specs/survey-system/03-test-plan.csv
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
  AdminFixture,
} from './helpers';

interface RespondentInput {
  optionLabel: string;
  departmentName?: string;
  gender?: string;
  birthDate?: string;
  hiredMonth?: string;
}

/**
 * 管理者ログイン状態から、指定属性のユーザーを登録して回答させ、管理者に戻る。
 */
async function respond(
  page: Page,
  fixture: AdminFixture,
  title: string,
  index: number,
  input: RespondentInput,
): Promise<void> {
  const email = `res${index}-${fixture.companyCode}@example.com`;
  await registerUser(page, {
    role: 'user',
    name: `回答者${index}`,
    email,
    departmentName: input.departmentName,
    gender: input.gender,
    birthDate: input.birthDate,
    hiredMonth: input.hiredMonth,
  });
  await logout(page);
  await login(page, fixture.companyCode, email, PASSWORD);
  await answerSurvey(page, title, input.optionLabel);
  await logout(page);
  await login(page, fixture.companyCode, fixture.adminEmail, PASSWORD);
}

async function addDepartment(page: Page, name: string): Promise<void> {
  await page.goto('/departments');
  await page.fill('#name', name);
  await page.getByRole('button', { name: '登録', exact: true }).click();
}

/** ダッシュボードで対象アンケートを選択し、初回描画を待つ */
async function openDashboard(page: Page, title: string): Promise<void> {
  await page.goto('/dashboard');
  await page.selectOption('#survey-select', { label: title });
  await expect(page.getByTestId('total-responses')).not.toBeEmpty();
}

/** N 歳ちょうどの誕生日（今日基準）から offsetDays ずらした日付を返す */
function birthDateForAge(age: number, offsetDays = 0): string {
  const d = new Date();
  d.setFullYear(d.getFullYear() - age);
  d.setDate(d.getDate() + offsetDays);
  return d.toISOString().slice(0, 10);
}

test('E2E-129-dsp ダッシュボード(縦棒グラフ表示) 集計データが表示される', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e129');
  const title = `棒グラフ${fixture.companyCode}`;
  await createSurvey(page, { title });
  await respond(page, fixture, title, 1, { optionLabel: '満足' });

  await openDashboard(page, title);

  await expect(page.getByTestId('total-responses')).toHaveText('1 件');
  const canvas = page.locator('#question-results canvas').first();
  await expect(canvas).toHaveAttribute('data-chart-type', 'bar');
  expect(await chartValues(page)).toEqual({ 満足: 1, 普通: 0, 不満: 0 });
});

test('E2E-130-evt グラフ形式切替(円グラフ) 種別が変わり集計データは変わらない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e130');
  const title = `円グラフ${fixture.companyCode}`;
  await createSurvey(page, { title });
  await respond(page, fixture, title, 1, { optionLabel: '満足' });
  await openDashboard(page, title);

  await page.selectOption('#chart-type-select', 'pie');

  const canvas = page.locator('#question-results canvas').first();
  await expect(canvas).toHaveAttribute('data-chart-type', 'pie');
  expect(await chartValues(page)).toEqual({ 満足: 1, 普通: 0, 不満: 0 });
});

test('E2E-131-evt 絞り込み(部署一致) 回答件数が 1 件になる', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e131');
  await addDepartment(page, '営業部');
  const title = `部署一致${fixture.companyCode}`;
  await createSurvey(page, { title });
  await respond(page, fixture, title, 1, { optionLabel: '満足', departmentName: '営業部' });
  await openDashboard(page, title);

  await page.selectOption('#filter-department', { label: '営業部' });
  await page.getByRole('button', { name: '絞り込み' }).click();

  await expect(page.getByTestId('total-responses')).toHaveText('1 件');
});

test('E2E-132-evt 絞り込み(部署不一致) 回答件数が 0 件になる', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e132');
  await addDepartment(page, '営業部');
  await addDepartment(page, '総務部');
  const title = `部署不一致${fixture.companyCode}`;
  await createSurvey(page, { title });
  await respond(page, fixture, title, 1, { optionLabel: '満足', departmentName: '営業部' });
  await openDashboard(page, title);

  await page.selectOption('#filter-department', { label: '総務部' });
  await page.getByRole('button', { name: '絞り込み' }).click();

  await expect(page.getByTestId('total-responses')).toHaveText('0 件');
});

test('E2E-133-evt 絞り込み(指定なし) 全回答 2 件が集計される', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e133');
  await addDepartment(page, '営業部');
  await addDepartment(page, '総務部');
  const title = `指定なし${fixture.companyCode}`;
  await createSurvey(page, { title });
  await respond(page, fixture, title, 1, { optionLabel: '満足', departmentName: '営業部' });
  await respond(page, fixture, title, 2, { optionLabel: '普通', departmentName: '総務部' });

  await openDashboard(page, title);
  await page.getByRole('button', { name: '絞り込み' }).click();

  await expect(page.getByTestId('total-responses')).toHaveText('2 件');
  expect(await chartValues(page)).toEqual({ 満足: 1, 普通: 1, 不満: 0 });
});

test('E2E-134-evt 絞り込み(回答日時・範囲内) 回答件数が 1 件になる', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e134');
  const title = `日時範囲内${fixture.companyCode}`;
  await createSurvey(page, { title });
  await respond(page, fixture, title, 1, { optionLabel: '満足' });
  await openDashboard(page, title);

  const yesterday = new Date(Date.now() - 86_400_000).toISOString().slice(0, 10);
  const today = new Date().toISOString().slice(0, 10);
  await page.fill('#filter-date-from', yesterday);
  await page.fill('#filter-date-to', today);
  await page.getByRole('button', { name: '絞り込み' }).click();

  await expect(page.getByTestId('total-responses')).toHaveText('1 件');
});

test('E2E-135-evt 絞り込み(性別) 女性の回答のみ 1 件になる', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e135');
  const title = `性別絞込${fixture.companyCode}`;
  await createSurvey(page, { title });
  await respond(page, fixture, title, 1, { optionLabel: '満足', gender: '男性' });
  await respond(page, fixture, title, 2, { optionLabel: '普通', gender: '女性' });
  await openDashboard(page, title);

  await page.selectOption('#filter-gender', { label: '女性' });
  await page.getByRole('button', { name: '絞り込み' }).click();

  await expect(page.getByTestId('total-responses')).toHaveText('1 件');
  expect(await chartValues(page)).toEqual({ 満足: 0, 普通: 1, 不満: 0 });
});

test('E2E-136-evt 絞り込み(年代) 20 代の回答のみ 1 件になる', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e136');
  const title = `年代絞込${fixture.companyCode}`;
  await createSurvey(page, { title });
  await respond(page, fixture, title, 1, { optionLabel: '満足', birthDate: birthDateForAge(25) });
  await respond(page, fixture, title, 2, { optionLabel: '普通', birthDate: birthDateForAge(45) });
  await openDashboard(page, title);

  await page.selectOption('#filter-age-group', { label: '20 代' });
  await page.getByRole('button', { name: '絞り込み' }).click();

  await expect(page.getByTestId('total-responses')).toHaveText('1 件');
  expect(await chartValues(page)).toEqual({ 満足: 1, 普通: 0, 不満: 0 });
});

test('E2E-137-evt 絞り込み(入社年月) 2020-04 入社のみ 1 件になる', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e137');
  const title = `入社絞込${fixture.companyCode}`;
  await createSurvey(page, { title });
  await respond(page, fixture, title, 1, { optionLabel: '満足', hiredMonth: '2015-04' });
  await respond(page, fixture, title, 2, { optionLabel: '普通', hiredMonth: '2020-04' });
  await openDashboard(page, title);

  await page.fill('#filter-hired-from', '2019-01');
  await page.fill('#filter-hired-to', '2021-12');
  await page.getByRole('button', { name: '絞り込み' }).click();

  await expect(page.getByTestId('total-responses')).toHaveText('1 件');
  expect(await chartValues(page)).toEqual({ 満足: 0, 普通: 1, 不満: 0 });
});

test('E2E-138-dsp 属性未設定(絞り込み時除外) 部署未設定の回答は件数に含まれない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e138');
  await addDepartment(page, '営業部');
  const title = `未設定除外${fixture.companyCode}`;
  await createSurvey(page, { title });
  await respond(page, fixture, title, 1, { optionLabel: '満足' }); // 部署未設定

  await openDashboard(page, title);
  await page.selectOption('#filter-department', { label: '営業部' });
  await page.getByRole('button', { name: '絞り込み' }).click();

  await expect(page.getByTestId('total-responses')).toHaveText('0 件');
});

test('E2E-139-dsp 属性未設定(絞り込みなしで含む) 回答件数が 1 件になる', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e139');
  const title = `未設定包含${fixture.companyCode}`;
  await createSurvey(page, { title });
  await respond(page, fixture, title, 1, { optionLabel: '満足' }); // 部署未設定

  await openDashboard(page, title);

  await expect(page.getByTestId('total-responses')).toHaveText('1 件');
});

test('E2E-140-dsp 自由記述の回答はテキスト一覧で表示される', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e140');
  const title = `自由記述${fixture.companyCode}`;
  await createSurvey(page, { title, question: { type: 'text' } });
  // 自由記述に「改善希望」と回答する
  const email = `res1-${fixture.companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: '回答者1', email });
  await logout(page);
  await login(page, fixture.companyCode, email, PASSWORD);
  await page.getByRole('link', { name: title }).click();
  await page.locator('textarea[name^="answers"]').fill('改善希望');
  await page.getByRole('button', { name: '回答を送信する' }).click();
  await expect(page.getByTestId('flash-success')).toContainText('回答を送信しました');
  await logout(page);
  await login(page, fixture.companyCode, fixture.adminEmail, PASSWORD);

  await openDashboard(page, title);

  const textList = page.locator('[data-testid^="text-answers-"]');
  await expect(textList).toContainText('改善希望');
  await expect(page.locator('#question-results canvas')).toHaveCount(0);
});

test('E2E-141-dsp 初期表示は最新の公開アンケートが選択される', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e141');
  const oldTitle = `旧アンケート${fixture.companyCode}`;
  const newTitle = `新アンケート${fixture.companyCode}`;
  await createSurvey(page, { title: oldTitle });
  await createSurvey(page, { title: newTitle });

  await page.goto('/dashboard');

  await expect(page.locator('#survey-select option:checked')).toHaveText(newTitle);
  await expect(page.getByTestId('total-responses')).not.toBeEmpty();
});

test('E2E-142-dsp アンケート選択肢に下書きは表示されない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e142');
  const published = `公開分${fixture.companyCode}`;
  const draft = `下書き分${fixture.companyCode}`;
  await createSurvey(page, { title: published });
  await createSurvey(page, { title: draft, action: 'draft' });

  await page.goto('/dashboard');

  await expect(page.locator('#survey-select option', { hasText: published })).toHaveCount(1);
  await expect(page.locator('#survey-select option', { hasText: draft })).toHaveCount(0);
});

test('E2E-143-dsp 削除済みユーザーの回答が集計に含まれたまま表示される', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e143');
  const title = `削除回答${fixture.companyCode}`;
  await createSurvey(page, { title });
  await respond(page, fixture, title, 1, { optionLabel: '満足' });
  // 回答したユーザーを削除する
  await page.goto('/users');
  page.on('dialog', (dialog) => dialog.accept());
  await page
    .locator('tbody tr', { hasText: `res1-${fixture.companyCode}@example.com` })
    .getByRole('button', { name: '削除' })
    .click();

  await openDashboard(page, title);

  await expect(page.getByTestId('total-responses')).toHaveText('1 件');
  expect(await chartValues(page)).toEqual({ 満足: 1, 普通: 0, 不満: 0 });
});

test('E2E-144-dsp 回答0件の表示は 0 件・全選択肢 0 になる', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e144');
  const title = `回答なし${fixture.companyCode}`;
  await createSurvey(page, { title });

  await openDashboard(page, title);

  await expect(page.getByTestId('total-responses')).toHaveText('0 件');
  expect(await chartValues(page)).toEqual({ 満足: 0, 普通: 0, 不満: 0 });
});

test('E2E-183-evt 絞り込み(回答日時・範囲外) 回答件数が 0 件になる', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e183');
  const title = `日時範囲外${fixture.companyCode}`;
  await createSurvey(page, { title });
  await respond(page, fixture, title, 1, { optionLabel: '満足' });
  await openDashboard(page, title);

  const tomorrow = new Date(Date.now() + 86_400_000).toISOString().slice(0, 10);
  const dayAfter = new Date(Date.now() + 2 * 86_400_000).toISOString().slice(0, 10);
  await page.fill('#filter-date-from', tomorrow);
  await page.fill('#filter-date-to', dayAfter);
  await page.getByRole('button', { name: '絞り込み' }).click();

  await expect(page.getByTestId('total-responses')).toHaveText('0 件');
});
