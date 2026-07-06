import { execSync } from 'node:child_process';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import {
  USERS,
  EQUIPMENTS,
  resetDatabase,
  dateFromToday,
  apiList,
  apiCreate,
  apiUpdateStatus,
  openPage,
  selectUser,
  search,
  submitLoanForm,
} from './equipmentLoanHelpers';

const repoRoot = path.resolve(__dirname, '..', '..', '..');

/** DB へ直接 SQL を実行する（過去日データ等、API では作れない前提データ用） */
function runSql(sql: string): void {
  execSync(`docker compose exec -T db psql -U postgres -d laravel_db -c "${sql}"`, {
    cwd: repoRoot,
    stdio: 'pipe',
  });
}

// DB を共有するため、ファイル内は直列実行（fullyParallel を無効化）
test.describe.configure({ mode: 'default' });

test.beforeEach(() => {
  resetDatabase();
});

// E2E-trn-001: /equipment-loans 初期表示
test('備品貸出管理画面が表示され初期ユーザーは最初の一般社員', async ({ page }) => {
  await openPage(page);

  await expect(page.locator('h1')).toHaveText('備品貸出管理');
  await expect(page.locator('#mock-user-select')).toHaveValue(String(USERS.yamada));
  await expect(page.locator('#overdue-alert')).toBeAttached();
  await expect(page.locator('#loan-table-body')).toBeAttached();
});

// E2E-inp-001: 現在のユーザー select
test('ユーザー選択肢に全ユーザーが表示され初期値は山田 太郎', async ({ page }) => {
  await openPage(page);

  const options = page.locator('#mock-user-select option');
  await expect(options).toHaveCount(3);
  await expect(options.nth(0)).toContainText('山田 太郎');
  await expect(options.nth(1)).toContainText('佐藤 花子');
  await expect(options.nth(2)).toContainText('管理 太郎');
  await expect(page.locator('#mock-user-select')).toHaveValue(String(USERS.yamada));
});

// E2E-inp-002: 新規申請 必須項目未入力
test('必須項目を空で送信するとエラーが表示され申請は登録されない', async ({ page }) => {
  await openPage(page);
  const rowCount = await page.locator('[data-testid="loan-row"]').count();

  await page.click('[data-testid="loan-submit-button"]');

  await expect(page.locator('#error-message')).toBeVisible();
  await expect(page.locator('[data-testid="loan-row"]')).toHaveCount(rowCount);
});

// E2E-inp-003: 理由 任意入力なし
test('理由が空でも申請が登録される', async ({ page }) => {
  await openPage(page);
  const rowCount = await page.locator('[data-testid="loan-row"]').count();

  await submitLoanForm(page, {
    equipmentId: EQUIPMENTS.macbook,
    from: dateFromToday(30),
    to: dateFromToday(32),
  });

  await expect(page.locator('[data-testid="loan-row"]')).toHaveCount(rowCount + 1);
  await expect(page.locator('#error-message')).toBeHidden();
});

// E2E-inp-004: 貸出同日申請
test('開始日と終了日が同日の申請が登録される', async ({ page }) => {
  await openPage(page);
  const rowCount = await page.locator('[data-testid="loan-row"]').count();

  await submitLoanForm(page, {
    equipmentId: EQUIPMENTS.macbook,
    from: dateFromToday(40),
    to: dateFromToday(40),
  });

  await expect(page.locator('[data-testid="loan-row"]')).toHaveCount(rowCount + 1);
});

// E2E-inp-005: 貸出開始日 過去日
test('貸出開始日が過去日の場合はエラーが表示され申請は登録されない', async ({ page }) => {
  await openPage(page);
  const rowCount = await page.locator('[data-testid="loan-row"]').count();

  await submitLoanForm(page, {
    equipmentId: EQUIPMENTS.macbook,
    from: dateFromToday(-1),
    to: dateFromToday(1),
  });

  await expect(page.locator('#error-message')).toContainText('貸出開始日は本日以降の日付を入力してください');
  await expect(page.locator('[data-testid="loan-row"]')).toHaveCount(rowCount);
});

// E2E-inp-006: 貸出終了日が開始日より前
test('貸出終了日が開始日より前の場合はエラーが表示され申請は登録されない', async ({ page }) => {
  await openPage(page);
  const rowCount = await page.locator('[data-testid="loan-row"]').count();

  await submitLoanForm(page, {
    equipmentId: EQUIPMENTS.macbook,
    from: dateFromToday(32),
    to: dateFromToday(30),
  });

  await expect(page.locator('#error-message')).toContainText('貸出終了日は貸出開始日以降の日付を入力してください');
  await expect(page.locator('[data-testid="loan-row"]')).toHaveCount(rowCount);
});

// E2E-inp-007: mock_user_id 未指定
test('mock_user_id 未指定の API 呼び出しは 422 になる', async ({ request }) => {
  const response = await apiList(request);

  expect(response.status()).toBe(422);
  const body = await response.json();
  expect(body.message).toContain('操作ユーザーを選択してください');
});

// E2E-inp-008: mock_user_id 存在なし
test('存在しない mock_user_id の API 呼び出しは 404 になる', async ({ request }) => {
  const response = await apiList(request, { mock_user_id: 999999 });

  expect(response.status()).toBe(404);
  const body = await response.json();
  expect(body.message).toBe('操作ユーザーが見つかりません');
});

// E2E-inp-009: equipment_id 未指定
test('equipment_id 未指定の申請 API は 422 になり DB は変更されない', async ({ request }) => {
  const response = await apiCreate(request, {
    mock_user_id: USERS.yamada,
    requested_from: dateFromToday(30),
    requested_to: dateFromToday(31),
  });

  expect(response.status()).toBe(422);
  const body = await response.json();
  expect(body.message).toContain('備品を選択してください');

  const list = await apiList(request, { mock_user_id: USERS.kanri });
  expect((await list.json()).items).toHaveLength(3);
});

// E2E-inp-010: equipment_id 存在なし
test('存在しない equipment_id の申請 API は 404 になり DB は変更されない', async ({ request }) => {
  const response = await apiCreate(request, {
    mock_user_id: USERS.yamada,
    equipment_id: 999999,
    requested_from: dateFromToday(30),
    requested_to: dateFromToday(31),
  });

  expect(response.status()).toBe(404);
  const body = await response.json();
  expect(body.message).toBe('備品が見つかりません');

  const list = await apiList(request, { mock_user_id: USERS.kanri });
  expect((await list.json()).items).toHaveLength(3);
});

// E2E-dsp-001: 一般社員の申請一覧
test('一般社員には自分の申請のみ表示される', async ({ page }) => {
  await openPage(page);

  const rows = page.locator('[data-testid="loan-row"]');
  await expect(rows).toHaveCount(2);
  await expect(page.locator('#loan-table-body')).toContainText('山田 太郎');
  await expect(page.locator('#loan-table-body')).not.toContainText('佐藤 花子');
});

// E2E-dsp-002: 管理者の申請一覧
test('管理者には全ユーザーの申請が表示される', async ({ page }) => {
  await openPage(page);
  await selectUser(page, USERS.kanri);

  await expect(page.locator('[data-testid="loan-row"]')).toHaveCount(3);
  await expect(page.locator('#loan-table-body')).toContainText('山田 太郎');
  await expect(page.locator('#loan-table-body')).toContainText('佐藤 花子');
});

// E2E-dsp-003: ステータスバッジ
test('4 種類のステータスが色分けバッジで表示される', async ({ page, request }) => {
  // rejected の申請を API 経由で用意する
  const created = await apiCreate(request, {
    mock_user_id: USERS.sato,
    equipment_id: EQUIPMENTS.iphone,
    requested_from: dateFromToday(5),
    requested_to: dateFromToday(6),
  });
  const loanId = (await created.json()).item.id;
  await apiUpdateStatus(request, loanId, { mock_user_id: USERS.kanri, status: 'rejected' });

  await openPage(page);
  await selectUser(page, USERS.kanri);

  for (const [status, label] of [
    ['pending', '申請中'],
    ['approved', '貸出中'],
    ['returned', '返却済'],
    ['rejected', '却下'],
  ] as const) {
    const badge = page.locator(`[data-testid="status-badge"][data-status="${status}"]`).first();
    await expect(badge).toBeVisible();
    await expect(badge).toHaveText(label);
  }
});

// E2E-dsp-004: 一般社員 期限切れアラートあり
test('一般社員に期限切れ申請がある場合はアラートと注意アイコンが表示される', async ({ page }) => {
  await openPage(page);

  await expect(page.locator('#overdue-alert')).toBeVisible();
  await expect(page.locator('#overdue-alert')).toContainText('iPhone 15 Pro');
  await expect(page.locator('[data-testid="overdue-icon"]')).toHaveCount(1);
});

// E2E-dsp-005: 管理者 期限切れアラートあり
test('管理者には全ユーザー分の期限切れアラートが表示される', async ({ page }) => {
  // 佐藤 花子にも期限切れ申請を用意する（過去日のため DB 直接投入）
  runSql(
    "INSERT INTO equipment_loan_requests (user_id, equipment_id, status, requested_from, requested_to, reason, created_at, updated_at) VALUES (2, 3, 'approved', CURRENT_DATE - 8, CURRENT_DATE - 2, NULL, NOW(), NOW());",
  );

  await openPage(page);
  await selectUser(page, USERS.kanri);

  await expect(page.locator('#overdue-alert')).toBeVisible();
  await expect(page.locator('#overdue-alert')).toContainText('2 件');
  await expect(page.locator('#overdue-alert')).toContainText('山田 太郎');
  await expect(page.locator('#overdue-alert')).toContainText('佐藤 花子');
  await expect(page.locator('[data-testid="overdue-icon"]')).toHaveCount(2);
});

// E2E-dsp-006: 期限切れアラートなし
test('期限切れ申請がないユーザーではアラートとアイコンが表示されない', async ({ page }) => {
  await openPage(page);
  await selectUser(page, USERS.sato);

  await expect(page.locator('#overdue-alert')).toBeHidden();
  await expect(page.locator('[data-testid="overdue-icon"]')).toHaveCount(0);
});

// E2E-dsp-007: 検索結果 0件
test('一致しない検索文字列では一覧が 0 件表示になる', async ({ page }) => {
  await openPage(page);
  await search(page, '存在しない備品XYZ');

  await expect(page.locator('[data-testid="loan-row"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="loan-empty-row"]')).toBeVisible();
  await expect(page.locator('#error-message')).toBeHidden();
});

// E2E-auth-001: 一般社員の管理者ボタン非表示
test('一般社員にはステータス更新ボタンが表示されない', async ({ page }) => {
  await openPage(page);

  await expect(page.locator('[data-testid="loan-row"]').first()).toBeVisible();
  await expect(page.locator('[data-testid^="status-button-"]')).toHaveCount(0);
});

// E2E-auth-002: 管理者のステータス更新ボタン表示
test('管理者には各行に承認・却下・返却ボタンが表示される', async ({ page }) => {
  await openPage(page);
  await selectUser(page, USERS.kanri);

  const rows = page.locator('[data-testid="loan-row"]');
  const rowCount = await rows.count();
  for (let i = 0; i < rowCount; i++) {
    await expect(rows.nth(i).locator('[data-testid="status-button-approved"]')).toBeVisible();
    await expect(rows.nth(i).locator('[data-testid="status-button-rejected"]')).toBeVisible();
    await expect(rows.nth(i).locator('[data-testid="status-button-returned"]')).toBeVisible();
  }
});

// E2E-auth-003: 一般社員 API 直接ステータス更新不可
test('一般社員がステータス更新 API を直接呼ぶと 403 で更新されない', async ({ request }) => {
  const response = await apiUpdateStatus(request, 1, {
    mock_user_id: USERS.yamada,
    status: 'approved',
  });

  expect(response.status()).toBe(403);
  const body = await response.json();
  expect(body.message).toBe('ステータスを更新する権限がありません');

  const list = await apiList(request, { mock_user_id: USERS.kanri });
  const items = (await list.json()).items as { id: number; status: string }[];
  expect(items.find((i) => i.id === 1)?.status).toBe('pending');
});

// E2E-auth-004: 管理者 承認更新可
test('管理者が承認するとステータスが貸出中になる', async ({ page }) => {
  await openPage(page);
  await selectUser(page, USERS.kanri);

  const pendingRow = page.locator('[data-testid="loan-row"]', {
    has: page.locator('[data-testid="status-badge"][data-status="pending"]'),
  });
  await pendingRow.locator('[data-testid="status-button-approved"]').click();

  await expect(
    page.locator('[data-testid="status-badge"][data-status="pending"]'),
  ).toHaveCount(0);
  await expect(
    page.locator('[data-testid="status-badge"][data-status="approved"]'),
  ).toHaveCount(2);
});

// E2E-auth-005: 管理者 却下更新可
test('管理者が却下するとステータスが却下になる', async ({ page }) => {
  await openPage(page);
  await selectUser(page, USERS.kanri);

  const pendingRow = page.locator('[data-testid="loan-row"]', {
    has: page.locator('[data-testid="status-badge"][data-status="pending"]'),
  });
  await pendingRow.locator('[data-testid="status-button-rejected"]').click();

  await expect(
    page.locator('[data-testid="status-badge"][data-status="pending"]'),
  ).toHaveCount(0);
  await expect(
    page.locator('[data-testid="status-badge"][data-status="rejected"]'),
  ).toHaveCount(1);
});

// E2E-auth-006: 管理者も新規申請可
test('管理者も新規申請できる', async ({ page }) => {
  await openPage(page);
  await selectUser(page, USERS.kanri);
  const rowCount = await page.locator('[data-testid="loan-row"]').count();

  await submitLoanForm(page, {
    equipmentId: EQUIPMENTS.windowsPc,
    from: dateFromToday(5),
    to: dateFromToday(6),
  });

  await expect(page.locator('[data-testid="loan-row"]')).toHaveCount(rowCount + 1);
  await expect(
    page.locator('[data-testid="status-badge"][data-status="pending"]'),
  ).toHaveCount(2);
});

// E2E-evt-001: ユーザー切替
test('ユーザーを切り替えると一覧と管理者ボタン表示が切り替わる', async ({ page }) => {
  await openPage(page);
  await expect(page.locator('[data-testid="loan-row"]')).toHaveCount(2);
  await expect(page.locator('[data-testid^="status-button-"]')).toHaveCount(0);

  await selectUser(page, USERS.kanri);

  await expect(page.locator('[data-testid="loan-row"]')).toHaveCount(3);
  expect(await page.locator('[data-testid^="status-button-"]').count()).toBeGreaterThan(0);
});

// E2E-evt-002: 備品名検索 部分一致
test('備品名の部分一致検索で該当申請のみ表示される', async ({ page }) => {
  await openPage(page);
  await selectUser(page, USERS.kanri);
  await search(page, 'MacBook');

  const rows = page.locator('[data-testid="loan-row"]');
  await expect(rows).toHaveCount(1);
  await expect(rows.first()).toContainText('MacBook Pro M3');
});

// E2E-evt-003: 新規申請成功後の再取得
test('新規申請成功後に一覧が再取得され新規申請が表示される', async ({ page }) => {
  await openPage(page);
  const rowCount = await page.locator('[data-testid="loan-row"]').count();

  await Promise.all([
    page.waitForResponse((r) => r.url().includes('/api/equipment-loans') && r.request().method() === 'GET'),
    submitLoanForm(page, {
      equipmentId: EQUIPMENTS.macbook,
      from: dateFromToday(30),
      to: dateFromToday(31),
      reason: '再取得確認用',
    }),
  ]);

  await expect(page.locator('[data-testid="loan-row"]')).toHaveCount(rowCount + 1);
  await expect(page.locator('#loan-table-body')).toContainText('再取得確認用');
});

// E2E-evt-004: ステータス更新成功後の再取得
test('ステータス更新成功後に一覧が再取得されバッジが更新される', async ({ page }) => {
  await openPage(page);
  await selectUser(page, USERS.kanri);

  const pendingRow = page.locator('[data-testid="loan-row"]', {
    has: page.locator('[data-testid="status-badge"][data-status="pending"]'),
  });
  await Promise.all([
    page.waitForResponse((r) => r.url().includes('/api/equipment-loans') && r.request().method() === 'GET'),
    pendingRow.locator('[data-testid="status-button-approved"]').click(),
  ]);

  await expect(
    page.locator('[data-testid="status-badge"][data-status="pending"]'),
  ).toHaveCount(0);
});

// E2E-evt-005: API エラー表示
test('部署対象外の備品を申請するとエラー領域にメッセージが表示される', async ({ page }) => {
  await openPage(page);
  await selectUser(page, USERS.sato);

  await submitLoanForm(page, {
    equipmentId: EQUIPMENTS.macbook,
    from: dateFromToday(5),
    to: dateFromToday(6),
  });

  await expect(page.locator('#error-message')).toContainText('この備品は所属部署では申請できません');
});

// E2E-evt-006: 返却完了で期限切れアラート消失
test('期限切れ申請を返却するとアラートとアイコンが消える', async ({ page }) => {
  await openPage(page);
  await selectUser(page, USERS.kanri);
  await expect(page.locator('#overdue-alert')).toBeVisible();

  const overdueRow = page.locator('[data-testid="loan-row"]', {
    has: page.locator('[data-testid="overdue-icon"]'),
  });
  await overdueRow.locator('[data-testid="status-button-returned"]').click();

  await expect(page.locator('#overdue-alert')).toBeHidden();
  await expect(page.locator('[data-testid="overdue-icon"]')).toHaveCount(0);
  await expect(
    page.locator('[data-testid="status-badge"][data-status="returned"]'),
  ).toHaveCount(2);
});

// E2E-dyn-001: 部署制限 OK
test('部署が一致する備品の申請が登録される', async ({ page }) => {
  await openPage(page);
  const rowCount = await page.locator('[data-testid="loan-row"]').count();

  await submitLoanForm(page, {
    equipmentId: EQUIPMENTS.macbook,
    from: dateFromToday(30),
    to: dateFromToday(31),
  });

  await expect(page.locator('[data-testid="loan-row"]')).toHaveCount(rowCount + 1);
});

// E2E-dyn-002: 部署制限 NG
test('部署が一致しない備品の申請は登録されない', async ({ page }) => {
  await openPage(page);
  await selectUser(page, USERS.sato);
  const rowCount = await page.locator('[data-testid="loan-row"]').count();

  await submitLoanForm(page, {
    equipmentId: EQUIPMENTS.macbook,
    from: dateFromToday(5),
    to: dateFromToday(6),
  });

  await expect(page.locator('#error-message')).toContainText('この備品は所属部署では申請できません');
  await expect(page.locator('[data-testid="loan-row"]')).toHaveCount(rowCount);
});

// E2E-dyn-003: 部署制限なし備品 OK
test('対象部署が NULL の備品は部署に関係なく申請できる', async ({ page }) => {
  await openPage(page);
  await selectUser(page, USERS.sato);
  const rowCount = await page.locator('[data-testid="loan-row"]').count();

  await submitLoanForm(page, {
    equipmentId: EQUIPMENTS.iphone,
    from: dateFromToday(5),
    to: dateFromToday(6),
  });

  await expect(page.locator('[data-testid="loan-row"]')).toHaveCount(rowCount + 1);
  await expect(
    page.locator('[data-testid="status-badge"][data-status="pending"]'),
  ).toHaveCount(1);
});

// E2E-dyn-004: 同一ユーザー重複期間 NG
test('重複期間の申請はエラーになり登録されない', async ({ page }) => {
  await openPage(page);
  const rowCount = await page.locator('[data-testid="loan-row"]').count();

  // 山田 太郎は +14〜+16 に pending 申請があるため重複させる
  await submitLoanForm(page, {
    equipmentId: EQUIPMENTS.iphone,
    from: dateFromToday(15),
    to: dateFromToday(17),
  });

  await expect(page.locator('#error-message')).toContainText('指定期間は既に別の備品を申請中または貸出中です');
  await expect(page.locator('[data-testid="loan-row"]')).toHaveCount(rowCount);
});

// E2E-dyn-005: 在庫数上限 NG
test('在庫数を超える期間の申請はエラーになり登録されない', async ({ page, request }) => {
  // iPhone（在庫 1）に approved の申請を用意する
  const created = await apiCreate(request, {
    mock_user_id: USERS.sato,
    equipment_id: EQUIPMENTS.iphone,
    requested_from: dateFromToday(20),
    requested_to: dateFromToday(22),
  });
  const loanId = (await created.json()).item.id;
  await apiUpdateStatus(request, loanId, { mock_user_id: USERS.kanri, status: 'approved' });

  await openPage(page);
  await selectUser(page, USERS.kanri);
  const rowCount = await page.locator('[data-testid="loan-row"]').count();

  await submitLoanForm(page, {
    equipmentId: EQUIPMENTS.iphone,
    from: dateFromToday(21),
    to: dateFromToday(21),
  });

  await expect(page.locator('#error-message')).toContainText('指定期間は備品の在庫数を超えています');
  await expect(page.locator('[data-testid="loan-row"]')).toHaveCount(rowCount);
});

// E2E-other-001: API レスポンス形式
test('一覧 API のレスポンスに必要なキーが含まれる', async ({ request }) => {
  const response = await apiList(request, { mock_user_id: USERS.yamada });

  expect(response.status()).toBe(200);
  const body = await response.json();
  for (const key of ['viewer', 'items', 'overdue_summary', 'users', 'equipments']) {
    expect(body).toHaveProperty(key);
  }
  expect(body.items.length).toBeGreaterThan(0);
  expect(body.items[0]).toHaveProperty('is_overdue');
  expect(body.items[0]).toHaveProperty('can_update_status');
});

// E2E-other-002: 対象外機能が表示されない
test('対象外の備品マスタ編集・ユーザー編集・通知設定は表示されない', async ({ page }) => {
  await openPage(page);

  await expect(page.locator('body')).not.toContainText('備品マスタ編集');
  await expect(page.locator('body')).not.toContainText('ユーザー編集');
  await expect(page.locator('body')).not.toContainText('通知設定');
});
