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
