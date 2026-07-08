// ユーザー管理（E2E-030〜E2E-053, E2E-164, E2E-169, E2E-177, E2E-178, E2E-184, E2E-185）
// 出典: docs/specs/survey-system/03-test-plan.csv
import { test, expect } from '@playwright/test';
import {
  PASSWORD,
  createCompany,
  login,
  loginAsSU,
  logout,
  registerUser,
  setupAdmin,
  setupCompany,
  uid,
} from './helpers';

test('E2E-030-inp SU によるユーザー登録後にそのアカウントでログインできる', async ({ page }) => {
  const fixture = await setupCompany(page, 'e2e030');
  const email = `user-${fixture.companyCode}@example.com`;

  await registerUser(page, {
    companyName: fixture.companyName,
    role: 'user',
    name: 'SU登録ユーザー',
    email,
  });
  await logout(page);
  await login(page, fixture.companyCode, email, PASSWORD);

  await expect(page).toHaveURL(/\/my\/surveys$/);
});

test('E2E-031-inp SU による管理者登録後にそのアカウントでログインできる', async ({ page }) => {
  const fixture = await setupCompany(page, 'e2e031');
  const email = `admin-${fixture.companyCode}@example.com`;

  await registerUser(page, {
    companyName: fixture.companyName,
    role: 'admin',
    name: 'SU登録管理者',
    email,
  });
  await logout(page);
  await login(page, fixture.companyCode, email, PASSWORD);

  await expect(page).toHaveURL(/\/dashboard$/);
});

test('E2E-032-inp 管理者によるユーザー登録（企業選択欄なしで自社固定）', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e032');
  const email = `member-${fixture.companyCode}@example.com`;

  await page.goto('/users');
  await expect(page.locator('#company_id')).toHaveCount(0);
  await registerUser(page, { role: 'user', name: '管理者登録ユーザー', email });
  await logout(page);
  await login(page, fixture.companyCode, email, PASSWORD);

  await expect(page).toHaveURL(/\/my\/surveys$/);
});

test('E2E-033-inp 管理者による管理者登録後にそのアカウントでログインできる', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e033');
  const email = `admin2-${fixture.companyCode}@example.com`;

  await registerUser(page, { role: 'admin', name: '追加管理者', email });
  await logout(page);
  await login(page, fixture.companyCode, email, PASSWORD);

  await expect(page).toHaveURL(/\/dashboard$/);
});

test('E2E-034-inp ユーザー登録(氏名未入力) 必須エラーで登録されない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e034');
  const email = `noname-${fixture.companyCode}@example.com`;
  await page.goto('/users');

  await page.fill('#email', email);
  await page.fill('#password', PASSWORD);
  await page.getByRole('button', { name: '登録', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('氏名は必須です');
  await expect(page.locator('tbody tr', { hasText: email })).toHaveCount(0);
});

test('E2E-035-inp ユーザー登録(メール未入力) 必須エラーで登録されない', async ({ page }) => {
  await setupAdmin(page, 'e2e035');
  await page.goto('/users');

  await page.fill('#name', 'メールなし');
  await page.fill('#password', PASSWORD);
  await page.getByRole('button', { name: '登録', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('メールアドレスは必須です');
  await expect(page.locator('tbody tr', { hasText: 'メールなし' })).toHaveCount(0);
});

test('E2E-036-inp ユーザー登録(パスワード未入力) 必須エラーで登録されない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e036');
  const email = `nopass-${fixture.companyCode}@example.com`;
  await page.goto('/users');

  await page.fill('#name', 'パスワードなし');
  await page.fill('#email', email);
  await page.getByRole('button', { name: '登録', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('パスワードは必須です');
  await expect(page.locator('tbody tr', { hasText: email })).toHaveCount(0);
});

test('E2E-037-inp ユーザー登録(メール形式不正) 形式エラーで登録されない', async ({ page }) => {
  await setupAdmin(page, 'e2e037');
  await page.goto('/users');

  await page.fill('#name', '形式不正');
  await page.fill('#email', 'aaa');
  await page.fill('#password', PASSWORD);
  await page.getByRole('button', { name: '登録', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('メールアドレスの形式が正しくありません');
  await expect(page.locator('tbody tr', { hasText: '形式不正' })).toHaveCount(0);
});

test('E2E-038-inp ユーザー登録(同一企業メール重複) 重複エラーで登録されない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e038');
  const email = `taro-${fixture.companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: '既存太郎', email });
  await page.goto('/users');

  await page.fill('#name', '重複太郎');
  await page.fill('#email', email);
  await page.fill('#password', PASSWORD);
  await page.getByRole('button', { name: '登録', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('このメールアドレスは既に登録されています');
  await expect(page.locator('tbody tr', { hasText: email })).toHaveCount(1);
});

test('E2E-039-inp ユーザー登録(別企業なら同一メール可) 両企業でログインできる', async ({ page }) => {
  const email = `taro-${uid('e039')}@example.com`;
  const a = await setupCompany(page, 'e2e039a');
  await registerUser(page, { companyName: a.companyName, role: 'user', name: 'A社太郎', email });
  const codeB = uid('e039b');
  const nameB = `企業${codeB}`;
  await createCompany(page, nameB, codeB);

  await registerUser(page, { companyName: nameB, role: 'user', name: 'B社太郎', email });
  await logout(page);

  await login(page, a.companyCode, email, PASSWORD);
  await expect(page).toHaveURL(/\/my\/surveys$/);
  await logout(page);
  await login(page, codeB, email, PASSWORD);
  await expect(page).toHaveURL(/\/my\/surveys$/);
});

test('E2E-040-inp ユーザー登録(パスワード7文字) 文字数エラーで登録されない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e040');
  const email = `p7-${fixture.companyCode}@example.com`;
  await page.goto('/users');

  await page.fill('#name', '短パス');
  await page.fill('#email', email);
  await page.fill('#password', 'abcd123');
  await page.getByRole('button', { name: '登録', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('パスワードは 8 文字以上で入力してください');
  await expect(page.locator('tbody tr', { hasText: email })).toHaveCount(0);
});

test('E2E-041-inp ユーザー登録(パスワード8文字境界) 登録成功しログインできる', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e041');
  const email = `p8-${fixture.companyCode}@example.com`;

  await registerUser(page, { role: 'user', name: '境界パス', email, password: 'abcd1234' });
  await logout(page);
  await login(page, fixture.companyCode, email, 'abcd1234');

  await expect(page).toHaveURL(/\/my\/surveys$/);
});

test('E2E-042-inp ユーザー登録(氏名101文字) 文字数エラーで登録されない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e042');
  const email = `n101-${fixture.companyCode}@example.com`;
  await page.goto('/users');

  await page.fill('#name', 'あ'.repeat(101));
  await page.fill('#email', email);
  await page.fill('#password', PASSWORD);
  await page.getByRole('button', { name: '登録', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('氏名は 100 文字以内で入力してください');
  await expect(page.locator('tbody tr', { hasText: email })).toHaveCount(0);
});

test('E2E-043-inp ユーザー登録(氏名100文字境界) 登録成功し一覧に表示される', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e043');
  const email = `n100-${fixture.companyCode}@example.com`;
  const name = 'あ'.repeat(100);

  await registerUser(page, { role: 'user', name, email });

  await expect(page.locator('tbody tr', { hasText: name })).toBeVisible();
});

test('E2E-044-evt ユーザー編集(部署変更) 一覧とダッシュボード集計に反映される', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e044');
  await page.goto('/departments');
  await page.fill('#name', '営業部');
  await page.getByRole('button', { name: '登録', exact: true }).click();
  await page.fill('#name', '総務部');
  await page.getByRole('button', { name: '登録', exact: true }).click();
  const email = `dept-${fixture.companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: '部署変更対象', email, departmentName: '営業部' });

  await page.locator('tbody tr', { hasText: email }).getByRole('link', { name: '編集' }).click();
  await page.selectOption('#department_id', { label: '総務部' });
  await page.getByRole('button', { name: '保存' }).click();

  await expect(page).toHaveURL(/\/users$/);
  await expect(page.locator('tbody tr', { hasText: email })).toContainText('総務部');
  // ダッシュボードの部署絞り込み選択肢にも総務部が存在する
  await page.goto('/dashboard');
  await expect(page.locator('#filter-department option', { hasText: '総務部' })).toHaveCount(1);
});

test('E2E-045-inp ユーザー編集(氏名空更新) 必須エラーで氏名は変わらない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e045');
  const email = `blank-${fixture.companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: '氏名A', email });

  await page.locator('tbody tr', { hasText: email }).getByRole('link', { name: '編集' }).click();
  await page.fill('#name', '');
  await page.getByRole('button', { name: '保存' }).click();

  await expect(page.getByTestId('form-errors')).toContainText('氏名は必須です');
  await page.goto('/users');
  await expect(page.locator('tbody tr', { hasText: email })).toContainText('氏名A');
});

test('E2E-046-inp ユーザー編集(パスワード空=変更なし) 従来のパスワードでログインできる', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e046');
  const email = `keep-${fixture.companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: '変更前', email, password: 'password-p1' });

  await page.locator('tbody tr', { hasText: email }).getByRole('link', { name: '編集' }).click();
  await page.fill('#name', '変更後');
  await page.getByRole('button', { name: '保存' }).click();
  await expect(page).toHaveURL(/\/users$/);
  await logout(page);

  await login(page, fixture.companyCode, email, 'password-p1');
  await expect(page).toHaveURL(/\/my\/surveys$/);
});

test('E2E-047-inp ユーザー編集(部署空上書き) 一覧の部署表示が未設定になる', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e047');
  await page.goto('/departments');
  await page.fill('#name', '営業部');
  await page.getByRole('button', { name: '登録', exact: true }).click();
  const email = `deptclear-${fixture.companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: '部署クリア', email, departmentName: '営業部' });

  await page.locator('tbody tr', { hasText: email }).getByRole('link', { name: '編集' }).click();
  await page.selectOption('#department_id', { label: '未設定' });
  await page.getByRole('button', { name: '保存' }).click();

  await expect(page.locator('tbody tr', { hasText: email })).toContainText('未設定');
});

test('E2E-048-inp ユーザー編集(性別未選択) 再表示で未回答になる', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e048');
  const email = `gender-${fixture.companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: '性別変更', email, gender: '男性' });

  await page.locator('tbody tr', { hasText: email }).getByRole('link', { name: '編集' }).click();
  await page.selectOption('#gender', { label: '未回答' });
  await page.getByRole('button', { name: '保存' }).click();
  await page.locator('tbody tr', { hasText: email }).getByRole('link', { name: '編集' }).click();

  await expect(page.locator('#gender option:checked')).toHaveText('未回答');
});

test('E2E-049-inp ユーザー編集(生年月日空上書き) 再表示で空になる', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e049');
  const email = `birth-${fixture.companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: '生年月日クリア', email, birthDate: '1990-04-15' });

  await page.locator('tbody tr', { hasText: email }).getByRole('link', { name: '編集' }).click();
  await page.fill('#birth_date', '');
  await page.getByRole('button', { name: '保存' }).click();
  await page.locator('tbody tr', { hasText: email }).getByRole('link', { name: '編集' }).click();

  await expect(page.locator('#birth_date')).toHaveValue('');
});

test('E2E-050-inp ユーザー編集(入社年月空上書き) 再表示で空になる', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e050');
  const email = `hired-${fixture.companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: '入社年月クリア', email, hiredMonth: '2015-04' });

  await page.locator('tbody tr', { hasText: email }).getByRole('link', { name: '編集' }).click();
  await page.fill('#hired_month', '');
  await page.getByRole('button', { name: '保存' }).click();
  await page.locator('tbody tr', { hasText: email }).getByRole('link', { name: '編集' }).click();

  await expect(page.locator('#hired_month')).toHaveValue('');
});

test('E2E-051-dsp ユーザー編集画面は企業とロールが変更不可', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e051');
  const email = `readonly-${fixture.companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: '編集画面確認', email });

  await page.locator('tbody tr', { hasText: email }).getByRole('link', { name: '編集' }).click();

  await expect(page.locator(`input[value="${fixture.companyName}"]`)).toBeDisabled();
  await expect(page.locator('input[value="ユーザー"]')).toBeDisabled();
  await expect(page.locator('#name')).toBeEnabled();
});

test('E2E-052-evt ユーザー削除後は一覧から消えログインできない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e052');
  const email = `deleted-${fixture.companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: '削除対象', email });
  page.on('dialog', (dialog) => dialog.accept());

  await page.locator('tbody tr', { hasText: email }).getByRole('button', { name: '削除' }).click();

  await expect(page.locator('tbody tr', { hasText: email })).toHaveCount(0);
  await logout(page);
  await login(page, fixture.companyCode, email, PASSWORD);
  await expect(page.getByTestId('form-errors')).toContainText('ログイン情報が正しくありません');
});

test('E2E-053-inp ユーザー登録(性別) 編集画面に女性が選択済みで表示される', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e053');
  const email = `female-${fixture.companyCode}@example.com`;

  await registerUser(page, { role: 'user', name: '性別登録', email, gender: '女性' });
  await page.locator('tbody tr', { hasText: email }).getByRole('link', { name: '編集' }).click();

  await expect(page.locator('#gender option:checked')).toHaveText('女性');
});

test('E2E-164-auth SU による他社ユーザー編集は保存できる', async ({ page }) => {
  const fixture = await setupCompany(page, 'e2e164');
  const email = `su-edit-${fixture.companyCode}@example.com`;
  await registerUser(page, {
    companyName: fixture.companyName,
    role: 'user',
    name: '編集前氏名',
    email,
  });

  await page.locator('tbody tr', { hasText: email }).getByRole('link', { name: '編集' }).click();
  await page.fill('#name', '編集後氏名');
  await page.getByRole('button', { name: '保存' }).click();

  await expect(page).toHaveURL(/\/users$/);
  await expect(page.locator('tbody tr', { hasText: email })).toContainText('編集後氏名');
});

test('E2E-169-inp ユーザー編集(メール空更新) 必須エラーでメールは変わらない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e169');
  const email = `m1-${fixture.companyCode}@example.com`;
  await registerUser(page, { role: 'user', name: 'メール空更新', email });

  await page.locator('tbody tr', { hasText: email }).getByRole('link', { name: '編集' }).click();
  await page.fill('#email', '');
  await page.getByRole('button', { name: '保存' }).click();

  await expect(page.getByTestId('form-errors')).toContainText('メールアドレスは必須です');
  await page.goto('/users');
  await expect(page.locator('tbody tr', { hasText: email })).toBeVisible();
});

test('E2E-177-inp ユーザー登録(パスワード255文字境界) 登録成功しログインできる', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e177');
  const email = `p255-${fixture.companyCode}@example.com`;
  const password = 'a'.repeat(255);

  await registerUser(page, { role: 'user', name: '長パス境界', email, password });
  await logout(page);
  await login(page, fixture.companyCode, email, password);

  await expect(page).toHaveURL(/\/my\/surveys$/);
});

test('E2E-178-inp ユーザー登録(パスワード256文字) 文字数エラーで登録されない', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e178');
  const email = `p256-${fixture.companyCode}@example.com`;
  await page.goto('/users');

  await page.fill('#name', '長パス超過');
  await page.fill('#email', email);
  await page.fill('#password', 'a'.repeat(256));
  await page.getByRole('button', { name: '登録', exact: true }).click();

  await expect(page.getByTestId('form-errors')).toContainText('パスワードは 255 文字以内で入力してください');
  await expect(page.locator('tbody tr', { hasText: email })).toHaveCount(0);
});

test('E2E-184-inp ユーザー登録(生年月日) 編集画面に表示される', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e184');
  const email = `bd-${fixture.companyCode}@example.com`;

  await registerUser(page, { role: 'user', name: '生年月日登録', email, birthDate: '1990-04-15' });
  await page.locator('tbody tr', { hasText: email }).getByRole('link', { name: '編集' }).click();

  await expect(page.locator('#birth_date')).toHaveValue('1990-04-15');
});

test('E2E-185-inp ユーザー登録(入社年月) 編集画面に表示される', async ({ page }) => {
  const fixture = await setupAdmin(page, 'e2e185');
  const email = `hm-${fixture.companyCode}@example.com`;

  await registerUser(page, { role: 'user', name: '入社年月登録', email, hiredMonth: '2015-04' });
  await page.locator('tbody tr', { hasText: email }).getByRole('link', { name: '編集' }).click();

  await expect(page.locator('#hired_month')).toHaveValue('2015-04');
});
