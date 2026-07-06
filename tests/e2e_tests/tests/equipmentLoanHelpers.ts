import { execSync } from 'node:child_process';
import path from 'node:path';
import { APIRequestContext, APIResponse, Page, expect } from '@playwright/test';

const repoRoot = path.resolve(__dirname, '..', '..', '..');

/** Seeder 投入データの ID（EquipmentLoanManagementSeeder 準拠） */
export const USERS = {
  yamada: 1, // 山田 太郎（開発部 / staff）
  sato: 2, // 佐藤 花子（総務部 / staff）
  kanri: 3, // 管理 太郎（総務部 / admin）
} as const;

export const EQUIPMENTS = {
  macbook: 1, // MacBook Pro M3（在庫 2 / 開発部）
  iphone: 2, // iPhone 15 Pro（在庫 1 / 全部署）
  windowsPc: 3, // 検証用 Windows PC（在庫 1 / 総務部）
} as const;

/** DB を Seeder 直後の状態に戻す */
export function resetDatabase(): void {
  execSync('docker compose exec -T app php artisan migrate:fresh --force --seed', {
    cwd: repoRoot,
    stdio: 'pipe',
  });
}

export function dateFromToday(days: number): string {
  const d = new Date();
  d.setDate(d.getDate() + days);
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

const JSON_HEADERS = { Accept: 'application/json' };

export async function apiList(
  request: APIRequestContext,
  params: Record<string, string | number> = {},
): Promise<APIResponse> {
  const query = new URLSearchParams(
    Object.fromEntries(Object.entries(params).map(([k, v]) => [k, String(v)])),
  ).toString();
  return request.get(`/api/equipment-loans${query ? `?${query}` : ''}`, { headers: JSON_HEADERS });
}

export async function apiCreate(
  request: APIRequestContext,
  data: Record<string, unknown>,
): Promise<APIResponse> {
  return request.post('/api/equipment-loans', { data, headers: JSON_HEADERS });
}

export async function apiUpdateStatus(
  request: APIRequestContext,
  loanId: number,
  data: Record<string, unknown>,
): Promise<APIResponse> {
  return request.patch(`/api/equipment-loans/${loanId}/status`, { data, headers: JSON_HEADERS });
}

/** /equipment-loans を開き、一覧の初回描画まで待つ */
export async function openPage(page: Page): Promise<void> {
  await Promise.all([
    page.waitForResponse((r) => r.url().includes('/api/equipment-loans') && r.request().method() === 'GET'),
    page.goto('/equipment-loans'),
  ]);
  await expect(page.locator('#loan-table-body tr').first()).toBeAttached();
}

/** 現在のユーザーを切り替え、一覧の再取得を待つ */
export async function selectUser(page: Page, userId: number): Promise<void> {
  await Promise.all([
    page.waitForResponse((r) => r.url().includes('/api/equipment-loans') && r.request().method() === 'GET'),
    page.selectOption('#mock-user-select', String(userId)),
  ]);
}

/** 備品名検索を実行し、一覧の再取得を待つ */
export async function search(page: Page, keyword: string): Promise<void> {
  await page.fill('#loan-search-input', keyword);
  await Promise.all([
    page.waitForResponse((r) => r.url().includes('/api/equipment-loans') && r.request().method() === 'GET'),
    page.click('#loan-search-button'),
  ]);
}

export interface LoanFormInput {
  equipmentId?: number;
  from?: string;
  to?: string;
  reason?: string;
}

/** 新規申請フォームを入力して送信する（レスポンスは待たずフォーム送信のみ） */
export async function submitLoanForm(page: Page, input: LoanFormInput): Promise<void> {
  if (input.equipmentId !== undefined) {
    await page.selectOption('#loan-equipment-select', String(input.equipmentId));
  }
  if (input.from !== undefined) {
    await page.fill('#loan-from-input', input.from);
  }
  if (input.to !== undefined) {
    await page.fill('#loan-to-input', input.to);
  }
  if (input.reason !== undefined) {
    await page.fill('#loan-reason-input', input.reason);
  }
  await page.click('[data-testid="loan-submit-button"]');
}
