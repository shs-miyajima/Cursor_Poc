// E2E 共通ヘルパー（survey-system）
// 各ケースは専用の企業（一意コード）を SU の画面操作で作成し、並列実行時の干渉を防ぐ。
import { Page, expect } from '@playwright/test';

export const SU_EMAIL = 'su@example.com';
export const SU_PASSWORD = 'password';
export const PASSWORD = 'pass12345';

let seq = 0;

/** 一意な半角英数文字列（企業コード・メールのローカル部などに使用） */
export function uid(prefix: string): string {
  seq += 1;
  return `${prefix}${Date.now().toString(36)}${Math.floor(Math.random() * 46656).toString(36)}${seq}`.toLowerCase();
}

export async function login(page: Page, companyCode: string, email: string, password: string): Promise<void> {
  await page.goto('/login');
  await page.fill('#company_code', companyCode);
  await page.fill('#email', email);
  await page.fill('#password', password);
  await page.getByRole('button', { name: 'ログイン' }).click();
}

export async function loginAsSU(page: Page): Promise<void> {
  await login(page, '', SU_EMAIL, SU_PASSWORD);
  await expect(page).toHaveURL(/\/admin\/home$/);
}

export async function logout(page: Page): Promise<void> {
  await page.getByRole('button', { name: 'ログアウト' }).click();
  await expect(page).toHaveURL(/\/login$/);
}

/** SU で企業を登録する（SU ログイン済み前提） */
export async function createCompany(page: Page, name: string, code: string): Promise<void> {
  await page.goto('/companies');
  await page.fill('#name', name);
  await page.fill('#code', code);
  await page.getByRole('button', { name: '登録', exact: true }).click();
  await expect(page.locator('tbody tr', { hasText: code })).toBeVisible();
}

export interface UserInput {
  role: 'user' | 'admin';
  name: string;
  email: string;
  password?: string;
  companyName?: string; // SU 全体ビューで登録する場合に指定
  departmentName?: string;
  gender?: string; // 表示ラベル（男性/女性/その他/未回答）
  birthDate?: string; // YYYY-MM-DD
  hiredMonth?: string; // YYYY-MM
}

/** ユーザー登録フォーム（S-04）で登録する。SU 全体ビューまたは管理者/企業ビューでログイン済み前提 */
export async function registerUser(page: Page, input: UserInput): Promise<void> {
  await page.goto('/users');
  if (input.companyName !== undefined) {
    await page.selectOption('#company_id', { label: input.companyName });
  }
  await page.selectOption('#role', input.role);
  await page.fill('#name', input.name);
  await page.fill('#email', input.email);
  await page.fill('#password', input.password ?? PASSWORD);
  if (input.departmentName !== undefined) {
    await page.selectOption('#department_id', { label: input.departmentName });
  }
  if (input.gender !== undefined) {
    await page.selectOption('#gender', { label: input.gender });
  }
  if (input.birthDate !== undefined) {
    await page.fill('#birth_date', input.birthDate);
  }
  if (input.hiredMonth !== undefined) {
    await page.fill('#hired_month', input.hiredMonth);
  }
  await page.getByRole('button', { name: '登録', exact: true }).click();
  await expect(page.locator('tbody tr', { hasText: input.email })).toBeVisible();
}

export interface CompanyFixture {
  companyName: string;
  companyCode: string;
}

export interface AdminFixture extends CompanyFixture {
  adminEmail: string;
}

export interface UserFixture extends CompanyFixture {
  userEmail: string;
  userName: string;
}

/** 専用企業を作成する（終了時は SU ログイン状態のまま） */
export async function setupCompany(page: Page, testId: string): Promise<CompanyFixture> {
  const code = uid(testId.replace(/[^a-z0-9]/gi, ''));
  const companyName = `企業${code}`;
  await loginAsSU(page);
  await createCompany(page, companyName, code);
  return { companyName, companyCode: code };
}

/** 専用企業 + 管理者を作成し、管理者でログインした状態にする */
export async function setupAdmin(page: Page, testId: string): Promise<AdminFixture> {
  const fixture = await setupCompany(page, testId);
  const adminEmail = `admin-${fixture.companyCode}@example.com`;
  await registerUser(page, {
    companyName: fixture.companyName,
    role: 'admin',
    name: `管理者${fixture.companyCode}`,
    email: adminEmail,
  });
  await logout(page);
  await login(page, fixture.companyCode, adminEmail, PASSWORD);
  await expect(page).toHaveURL(/\/dashboard$/);
  return { ...fixture, adminEmail };
}

/** 専用企業 + 一般ユーザーを作成し、ユーザーでログインした状態にする */
export async function setupUser(page: Page, testId: string): Promise<UserFixture> {
  const fixture = await setupCompany(page, testId);
  const userName = `ユーザー${fixture.companyCode}`;
  const userEmail = `user-${fixture.companyCode}@example.com`;
  await registerUser(page, {
    companyName: fixture.companyName,
    role: 'user',
    name: userName,
    email: userEmail,
  });
  await logout(page);
  await login(page, fixture.companyCode, userEmail, PASSWORD);
  await expect(page).toHaveURL(/\/my\/surveys$/);
  return { ...fixture, userEmail, userName };
}

/** SU の全体ビューから対象企業の企業ビューへ切り替える */
export async function switchToCompany(page: Page, companyName: string): Promise<void> {
  await page.goto('/admin/home');
  await page
    .locator('tbody tr', { hasText: companyName })
    .getByRole('button', { name: '企業ビューへ切替' })
    .click();
  await expect(page.getByTestId('selected-company')).toContainText(companyName);
}

export interface SurveyInput {
  title: string;
  description?: string;
  deadlineAt?: string; // datetime-local 形式（YYYY-MM-DDTHH:mm）
  companyName?: string; // SU 全体ビューで代行作成する場合
  question?: {
    body?: string;
    type?: 'single' | 'multiple' | 'text';
    required?: boolean;
    options?: string[]; // 省略時は 満足/普通/不満
  };
  action?: 'draft' | 'publish';
}

/**
 * アンケート作成画面（S-07）で 1 問構成のアンケートを作成する。
 * 管理者（または企業ビューの SU・全体ビューの SU + companyName 指定）でログイン済み前提。
 */
export async function createSurvey(page: Page, input: SurveyInput): Promise<void> {
  await page.goto('/surveys/create');
  if (input.companyName !== undefined) {
    await page.selectOption('#company_id', { label: input.companyName });
  }
  await page.fill('#title', input.title);
  if (input.description !== undefined) {
    await page.fill('#description', input.description);
  }
  if (input.deadlineAt !== undefined) {
    await page.fill('#deadline_at', input.deadlineAt);
  }

  const q = input.question ?? {};
  const type = q.type ?? 'single';
  await page.fill('textarea[name="questions[0][body]"]', q.body ?? '満足度を教えてください');
  await page.selectOption('select[name="questions[0][type]"]', type);
  if (q.required) {
    await page.check('input[name="questions[0][is_required]"]');
  }
  if (type !== 'text') {
    const options = q.options ?? ['満足', '普通', '不満'];
    for (let i = 0; i < options.length; i += 1) {
      if (i >= 2) {
        await page.getByTestId('add-option-0').click();
      }
      await page.fill(`input[name="questions[0][options][${i}]"]`, options[i]);
    }
  }

  const buttonName = (input.action ?? 'publish') === 'publish' ? '公開' : '下書き保存';
  await page.getByRole('button', { name: buttonName, exact: true }).click();
  await expect(page).toHaveURL(/\/surveys$/);
  await expect(page.locator('tbody tr', { hasText: input.title })).toBeVisible();
}

/** サーバー時刻（UTC）基準の datetime-local 文字列を返す（アプリの TZ は UTC のため） */
export function utcDatetimeLocal(offsetMinutes: number): string {
  const d = new Date(Date.now() + offsetMinutes * 60_000);
  return d.toISOString().slice(0, 16);
}

/** 未回答一覧からアンケートを開いて単一選択の選択肢を選び送信する（ユーザーでログイン済み前提） */
export async function answerSurvey(page: Page, surveyTitle: string, optionLabel: string): Promise<void> {
  await page.goto('/my/surveys');
  await page.getByRole('link', { name: surveyTitle }).click();
  await page.getByRole('radio', { name: optionLabel }).check();
  await page.getByRole('button', { name: '回答を送信する' }).click();
  await expect(page.getByTestId('flash-success')).toContainText('回答を送信しました');
}

/** CSV 文字列を Playwright のファイルペイロードにする */
export function csvFile(content: string, name = 'users.csv') {
  return { name, mimeType: 'text/csv', buffer: Buffer.from(content, 'utf-8') };
}

export const CSV_HEADER = '氏名,メールアドレス,パスワード,部署,性別,生年月日,入社年月';

/** CSV をアップロードする（/users/import 画面へ遷移して実行） */
export async function uploadCsv(page: Page, content: string, fileName = 'users.csv'): Promise<void> {
  await page.goto('/users/import');
  await page.getByTestId('csv-file').setInputFiles(csvFile(content, fileName));
  await page.getByRole('button', { name: 'アップロード' }).click();
}

/** グラフ canvas の data-chart-values 属性を解析して返す */
export async function chartValues(page: Page): Promise<Record<string, number>> {
  const canvas = page.locator('#question-results canvas').first();
  await expect(canvas).toBeVisible();
  return JSON.parse((await canvas.getAttribute('data-chart-values')) ?? '{}');
}

/**
 * 認証済みセッションで CSRF トークン付きフォームリクエストを送る（画面外操作の権限テスト用）。
 * 現在表示中のページの meta タグからトークンを取得するため、レイアウト付き画面を表示した状態で呼ぶ。
 */
export async function sendForm(
  page: Page,
  method: 'POST' | 'DELETE',
  path: string,
): Promise<number> {
  const token = await page.locator('meta[name="csrf-token"]').getAttribute('content');
  const response = await page.request.fetch(path, {
    method,
    form: { _token: token ?? '' },
    maxRedirects: 0,
    failOnStatusCode: false,
  });
  return response.status();
}
