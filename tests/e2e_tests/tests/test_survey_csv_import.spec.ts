// ユーザー CSV 取込（E2E-062〜E2E-083, E2E-165, E2E-179〜E2E-182）
// 出典: docs/specs/survey-system/03-test-plan.csv
import { test, expect } from '@playwright/test';
import {
  CSV_HEADER,
  PASSWORD,
  login,
  loginAsSU,
  logout,
  registerUser,
  setupAdmin,
  setupCompany,
  switchToCompany,
  uploadCsv,
} from './helpers';

function rowsCsv(rows: string[]): string {
  return [CSV_HEADER, ...rows].join('\n');
}

async function addDepartment(page, name: string): Promise<void> {
  await page.goto('/departments');
  await page.fill('#name', name);
  await page.getByRole('button', { name: '登録', exact: true }).click();
}

test('E2E-062-evt CSV 取込(全行新規) 確認画面経由で 3 名追加され完了メッセージが出る', async ({ page }) => {
  const { companyCode } = await setupAdmin(page, 'e2e062');
  await addDepartment(page, '営業部');
  const emails = [1, 2, 3].map((i) => `csv${i}-${companyCode}@example.com`);

  await uploadCsv(page, rowsCsv([
    `取込一郎,${emails[0]},pass12345,営業部,男性,1990-01-01,2015-04`,
    `取込二郎,${emails[1]},pass12345,,女性,,`,
    `取込三郎,${emails[2]},pass12345,,,,`,
  ]));

  await expect(page).toHaveURL(/\/users\/import\/confirm$/);
  await expect(page.getByTestId('import-summary')).toHaveText('新規 3 件・更新 0 件');
  for (const line of [1, 2, 3]) {
    await expect(page.getByTestId(`import-row-${line}`)).toContainText('新規');
  }
  await page.getByRole('button', { name: '確定' }).click();

  await expect(page).toHaveURL(/\/users$/);
  await expect(page.getByTestId('flash-success')).toContainText('新規 3 件・更新 0 件');
  for (const email of emails) {
    await expect(page.locator('tbody tr', { hasText: email })).toBeVisible();
  }
});

test('E2E-063-dsp CSV 確認画面に区分と件数サマリが表示される', async ({ page }) => {
  const { companyCode } = await setupAdmin(page, 'e2e063');
  const existing = `csv1-${companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: '既存ユーザー', email: existing });

  await uploadCsv(page, rowsCsv([
    `更新花子,${existing},pass12345,,,,`,
    `新規太郎,csv2-${companyCode}@example.com,pass12345,,,,`,
  ]));

  await expect(page.getByTestId('import-row-1')).toContainText('更新');
  await expect(page.getByTestId('import-row-2')).toContainText('新規');
  await expect(page.getByTestId('import-summary')).toHaveText('新規 1 件・更新 1 件');
});

test('E2E-064-evt CSV 取込(更新行の上書き) 氏名が更新され部署は空値上書きで未設定になる', async ({ page }) => {
  const { companyCode } = await setupAdmin(page, 'e2e064');
  await addDepartment(page, '営業部');
  const email = `upd-${companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: '旧名', email, departmentName: '営業部' });

  await uploadCsv(page, rowsCsv([`新名,${email},pass12345,,,,`]));
  await page.getByRole('button', { name: '確定' }).click();

  const row = page.locator('tbody tr', { hasText: email });
  await expect(row).toContainText('新名');
  await expect(row).toContainText('未設定');
});

test('E2E-065-evt CSV 取込(確認画面で戻る) 何も反映されない', async ({ page }) => {
  const { companyCode } = await setupAdmin(page, 'e2e065');
  await registerUser(page, { role: 'user', name: '既存ユーザー', email: `exists-${companyCode}@example.com` });
  await page.goto('/users');
  const before = await page.locator('tbody tr').count();

  await uploadCsv(page, rowsCsv([`新規太郎,back-${companyCode}@example.com,pass12345,,,,`]));
  await page.getByRole('link', { name: '戻る' }).click();

  await expect(page).toHaveURL(/\/users\/import$/);
  await page.goto('/users');
  await expect(page.locator('tbody tr')).toHaveCount(before);
});

test('E2E-066-inp CSV(ファイル未選択) 選択エラーが表示される', async ({ page }) => {
  await setupAdmin(page, 'e2e066');
  await page.goto('/users/import');

  await page.getByRole('button', { name: 'アップロード' }).click();

  await expect(page.getByTestId('form-errors')).toContainText('CSV ファイル(2MB 以内)を選択してください');
});

test('E2E-067-inp CSV(拡張子不正) 選択エラーで取り込まれない', async ({ page }) => {
  await setupAdmin(page, 'e2e067');
  await page.goto('/users/import');

  await page.getByTestId('csv-file').setInputFiles({
    name: 'users.xlsx',
    mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    buffer: Buffer.from('dummy'),
  });
  await page.getByRole('button', { name: 'アップロード' }).click();

  await expect(page.getByTestId('form-errors')).toContainText('CSV ファイル(2MB 以内)を選択してください');
});

test('E2E-068-inp CSV(2MB超) 選択エラーで取り込まれない', async ({ page }) => {
  await setupAdmin(page, 'e2e068');
  await page.goto('/users/import');
  // 2MB 超のダミー CSV を動的生成する
  const bigContent = `${CSV_HEADER}\n${'x'.repeat(2 * 1024 * 1024 + 1024)}`;

  await page.getByTestId('csv-file').setInputFiles({
    name: 'big.csv',
    mimeType: 'text/csv',
    buffer: Buffer.from(bigContent),
  });
  await page.getByRole('button', { name: 'アップロード' }).click();

  await expect(page.getByTestId('form-errors')).toContainText('CSV ファイル(2MB 以内)を選択してください');
});

test('E2E-069-inp CSV(501行) 行数エラーで 1 件も登録されない', async ({ page }) => {
  const { companyCode } = await setupAdmin(page, 'e2e069');
  const rows = Array.from({ length: 501 }, (_, i) => `ユーザー${i + 1},u${i + 1}-${companyCode}@example.com,pass12345,,,,`);

  await uploadCsv(page, rowsCsv(rows));

  await expect(page.getByTestId('form-errors')).toContainText('CSV は 500 行以内にしてください');
  await page.goto('/users');
  await expect(page.locator('tbody tr', { hasText: `u1-${companyCode}@example.com` })).toHaveCount(0);
});

test('E2E-070-inp CSV(500行境界) 500 件追加され完了メッセージが出る', async ({ page }) => {
  test.setTimeout(180_000);
  const { companyCode } = await setupAdmin(page, 'e2e070');
  const rows = Array.from({ length: 500 }, (_, i) => `ユーザー${i + 1},u${i + 1}-${companyCode}@example.com,pass12345,,,,`);

  await uploadCsv(page, rowsCsv(rows));
  await expect(page.getByTestId('import-summary')).toHaveText('新規 500 件・更新 0 件');
  await page.getByRole('button', { name: '確定' }).click();

  await expect(page.getByTestId('flash-success')).toContainText('新規 500 件・更新 0 件');
  await expect(page.locator('tbody tr', { hasText: `u500-${companyCode}@example.com` })).toBeVisible();
});

test('E2E-071-inp CSV(氏名欠落行) 行番号付きエラーで登録されない', async ({ page }) => {
  const { companyCode } = await setupAdmin(page, 'e2e071');

  await uploadCsv(page, rowsCsv([
    `正常一郎,ok1-${companyCode}@example.com,pass12345,,,,`,
    `,noname-${companyCode}@example.com,pass12345,,,,`,
    `正常三郎,ok3-${companyCode}@example.com,pass12345,,,,`,
  ]));

  await expect(page.getByTestId('form-errors')).toContainText('2 行目: 氏名 — 必須です');
  await page.goto('/users');
  await expect(page.locator('tbody tr', { hasText: `ok1-${companyCode}@example.com` })).toHaveCount(0);
});

test('E2E-072-inp CSV(メール形式不正行) エラーで 1 件も登録されない', async ({ page }) => {
  const { companyCode } = await setupAdmin(page, 'e2e072');

  await uploadCsv(page, rowsCsv([
    `正常一郎,ok1-${companyCode}@example.com,pass12345,,,,`,
    '不正二郎,aaa,pass12345,,,,',
  ]));

  await expect(page.getByTestId('form-errors')).toContainText('2 行目: メールアドレス — 形式が正しくありません');
  await page.goto('/users');
  await expect(page.locator('tbody tr', { hasText: `ok1-${companyCode}@example.com` })).toHaveCount(0);
});

test('E2E-073-inp CSV(ファイル内メール重複) エラーで 1 件も登録されない', async ({ page }) => {
  const { companyCode } = await setupAdmin(page, 'e2e073');
  const dup = `dup-${companyCode}@example.com`;

  await uploadCsv(page, rowsCsv([
    `一郎,${dup},pass12345,,,,`,
    `二郎,other-${companyCode}@example.com,pass12345,,,,`,
    `三郎,${dup},pass12345,,,,`,
  ]));

  await expect(page.getByTestId('form-errors')).toContainText('3 行目: メールアドレス — ファイル内で重複しています');
  await page.goto('/users');
  await expect(page.locator('tbody tr', { hasText: dup })).toHaveCount(0);
});

test('E2E-074-inp CSV(既存管理者と同一メール) 取込不可エラーになる', async ({ page }) => {
  const { adminEmail } = await setupAdmin(page, 'e2e074');

  await uploadCsv(page, rowsCsv([`管理者上書き,${adminEmail},pass12345,,,,`]));

  await expect(page.getByTestId('form-errors')).toContainText('1 行目: メールアドレス — 管理者のメールアドレスは取込できません');
});

test('E2E-075-inp CSV(パスワード7文字行) エラーで 1 件も登録されない', async ({ page }) => {
  const { companyCode } = await setupAdmin(page, 'e2e075');

  await uploadCsv(page, rowsCsv([
    `正常一郎,ok1-${companyCode}@example.com,pass12345,,,,`,
    `短パス二郎,p7-${companyCode}@example.com,abcd123,,,,`,
  ]));

  await expect(page.getByTestId('form-errors')).toContainText('2 行目: パスワード — 8 文字以上で入力してください');
  await page.goto('/users');
  await expect(page.locator('tbody tr', { hasText: `ok1-${companyCode}@example.com` })).toHaveCount(0);
});

test('E2E-076-inp CSV(性別不正値) エラーで登録されない', async ({ page }) => {
  const { companyCode } = await setupAdmin(page, 'e2e076');

  await uploadCsv(page, rowsCsv([`性別不正,g-${companyCode}@example.com,pass12345,,男,,`]));

  await expect(page.getByTestId('form-errors')).toContainText('1 行目: 性別 — 男性/女性/その他/未回答 のいずれかで入力してください');
});

test('E2E-077-inp CSV(生年月日形式不正) エラーで登録されない', async ({ page }) => {
  const { companyCode } = await setupAdmin(page, 'e2e077');

  await uploadCsv(page, rowsCsv([`日付不正,b-${companyCode}@example.com,pass12345,,,1990/04/15,`]));

  await expect(page.getByTestId('form-errors')).toContainText('1 行目: 生年月日 — YYYY-MM-DD 形式で入力してください');
});

test('E2E-078-inp CSV(入社年月形式不正) エラーで登録されない', async ({ page }) => {
  const { companyCode } = await setupAdmin(page, 'e2e078');

  await uploadCsv(page, rowsCsv([`年月不正,h-${companyCode}@example.com,pass12345,,,,2015-04-01`]));

  await expect(page.getByTestId('form-errors')).toContainText('1 行目: 入社年月 — YYYY-MM 形式で入力してください');
});

test('E2E-079-inp CSV(部署マスタ未登録) エラーで登録されない（自動登録しない）', async ({ page }) => {
  const { companyCode } = await setupAdmin(page, 'e2e079');

  await uploadCsv(page, rowsCsv([`部署未登録,d-${companyCode}@example.com,pass12345,企画部,,,`]));

  await expect(page.getByTestId('form-errors')).toContainText('1 行目: 部署 — 部署マスタに登録されていません');
  await page.goto('/departments');
  await expect(page.locator('tbody tr', { hasText: '企画部' })).toHaveCount(0);
});

test('E2E-080-dsp CSV(複数エラー全件表示) 3 行目と 5 行目のエラーが両方表示される', async ({ page }) => {
  const { companyCode } = await setupAdmin(page, 'e2e080');
  const dup = `dup-${companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: '既存ユーザー', email: `exists-${companyCode}@example.com` });

  const rows = [
    `一郎,${dup},pass12345,,,,`,
    `二郎,ok2-${companyCode}@example.com,pass12345,,,,`,
    `三郎,${dup},pass12345,,,,`, // 3 行目: ファイル内メール重複
    `四郎,ok4-${companyCode}@example.com,pass12345,,,,`,
    `五郎,ok5-${companyCode}@example.com,pass12345,存在しない部署,,,`, // 5 行目: 部署未登録
    ...Array.from({ length: 5 }, (_, i) => `追加${i + 6},ok${i + 6}-${companyCode}@example.com,pass12345,,,,`),
  ];
  await uploadCsv(page, rowsCsv(rows));

  const errors = page.getByTestId('form-errors');
  await expect(errors).toContainText('3 行目: メールアドレス — ファイル内で重複しています');
  await expect(errors).toContainText('5 行目: 部署 — 部署マスタに登録されていません');
  await page.goto('/users');
  await expect(page.locator('tbody tr', { hasText: `ok2-${companyCode}@example.com` })).toHaveCount(0);
});

test('E2E-081-inp CSV(部署の大文字小文字照合) マスタ表記で取込成功する', async ({ page }) => {
  const { companyCode } = await setupAdmin(page, 'e2e081');
  await addDepartment(page, '営業G');
  const email = `case-${companyCode}@example.com`;

  await uploadCsv(page, rowsCsv([`照合太郎,${email},pass12345,営業g,,,`]));
  await page.getByRole('button', { name: '確定' }).click();

  await expect(page.locator('tbody tr', { hasText: email })).toContainText('営業G');
});

test('E2E-082-inp CSV(メール大文字の更新判定) 更新区分で表示される', async ({ page }) => {
  const { companyCode } = await setupAdmin(page, 'e2e082');
  const local = `taro-${companyCode}`;
  await registerUser(page, { role: 'user', name: '既存太郎', email: `${local}@example.com` });

  await uploadCsv(page, rowsCsv([`大文字太郎,${local.toUpperCase()}@EXAMPLE.COM,pass12345,,,,`]));

  await expect(page.getByTestId('import-row-1')).toContainText('更新');
});

test('E2E-083-auth CSV 画面(SU 全体ビュー) 403 が表示される', async ({ page }) => {
  await loginAsSU(page);

  const response = await page.goto('/users/import');

  expect(response?.status()).toBe(403);
});

test('E2E-165-auth SU 企業ビューの CSV 画面はフォームが表示される', async ({ page }) => {
  const fixture = await setupCompany(page, 'e2e165');
  await switchToCompany(page, fixture.companyName);

  const response = await page.goto('/users/import');

  expect(response?.status()).toBe(200);
  await expect(page.getByTestId('csv-file')).toBeVisible();
  await expect(page.getByRole('button', { name: 'アップロード' })).toBeVisible();
});

test('E2E-179-inp CSV(パスワード255文字境界) 取込成功しログインできる', async ({ page }) => {
  const { companyCode } = await setupAdmin(page, 'e2e179');
  const email = `p255-${companyCode}@example.com`;
  const password = 'a'.repeat(255);

  await uploadCsv(page, rowsCsv([`長パス太郎,${email},${password},,,,`]));
  await page.getByRole('button', { name: '確定' }).click();
  await expect(page.locator('tbody tr', { hasText: email })).toBeVisible();
  await logout(page);

  await login(page, companyCode, email, password);
  await expect(page).toHaveURL(/\/my\/surveys$/);
});

test('E2E-180-inp CSV(パスワード256文字行) エラーで登録されない', async ({ page }) => {
  const { companyCode } = await setupAdmin(page, 'e2e180');

  await uploadCsv(page, rowsCsv([`超過太郎,p256-${companyCode}@example.com,${'a'.repeat(256)},,,,`]));

  await expect(page.getByTestId('form-errors')).toContainText('1 行目: パスワード — 8〜255 文字で入力してください');
});

test('E2E-181-inp CSV(氏名100文字境界) 取込成功し一覧に表示される', async ({ page }) => {
  const { companyCode } = await setupAdmin(page, 'e2e181');
  const email = `n100-${companyCode}@example.com`;
  const name = 'あ'.repeat(100);

  await uploadCsv(page, rowsCsv([`${name},${email},pass12345,,,,`]));
  await page.getByRole('button', { name: '確定' }).click();

  await expect(page.locator('tbody tr', { hasText: name })).toBeVisible();
});

test('E2E-182-inp CSV(氏名101文字行) エラーで登録されない', async ({ page }) => {
  const { companyCode } = await setupAdmin(page, 'e2e182');

  await uploadCsv(page, rowsCsv([`${'あ'.repeat(101)},n101-${companyCode}@example.com,pass12345,,,,`]));

  await expect(page.getByTestId('form-errors')).toContainText('1 行目: 氏名 — 100 文字以内で入力してください');
});
