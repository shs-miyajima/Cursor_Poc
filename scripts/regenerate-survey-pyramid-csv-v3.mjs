#!/usr/bin/env node
/**
 * survey-system: E2E 60 → 40（§1.5 棚卸し第3弾）
 * Usage: node scripts/regenerate-survey-pyramid-csv-v3.mjs
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const SPEC = join(ROOT, 'docs/specs/survey-system');

function parseCSV(content) {
  return content
    .split('\n')
    .filter((l) => l.trim())
    .map((line) => {
      const fields = [];
      let inQuote = false;
      let cur = '';
      for (const ch of line) {
        if (ch === '"') inQuote = !inQuote;
        else if (ch === ',' && !inQuote) {
          fields.push(cur);
          cur = '';
        } else cur += ch;
      }
      fields.push(cur);
      return fields;
    });
}

function csvEscape(value) {
  const s = String(value ?? '');
  if (s.includes(',') || s.includes('"') || s.includes('\n')) {
    return `"${s.replace(/"/g, '""')}"`;
  }
  return s;
}

function toCsvRow(fields) {
  return fields.map(csvEscape).join(',');
}

function e2eId(row) {
  return row[0].replace(/-(inp|evt|auth|trn|dsp|dyn)$/, '');
}

/** §1.5 棚卸し: 維持（40 件） */
const KEEP_E2E = new Set([
  'E2E-001', 'E2E-002', 'E2E-004', 'E2E-006', 'E2E-008', 'E2E-009',
  'E2E-011', 'E2E-012', 'E2E-021', 'E2E-022', 'E2E-023', 'E2E-026',
  'E2E-027', 'E2E-030', 'E2E-032', 'E2E-034', 'E2E-045', 'E2E-052',
  'E2E-054', 'E2E-055', 'E2E-060', 'E2E-061', 'E2E-062', 'E2E-066',
  'E2E-084', 'E2E-086', 'E2E-087', 'E2E-088', 'E2E-090', 'E2E-091',
  'E2E-108', 'E2E-117', 'E2E-118', 'E2E-120', 'E2E-126', 'E2E-128',
  'E2E-129', 'E2E-131', 'E2E-164', 'E2E-170',
]);

const INVENTORY = {
  維持: [...KEEP_E2E],
  移行: ['E2E-025', 'E2E-044', 'E2E-051', 'E2E-080', 'E2E-110', 'E2E-119'],
  削減: [
    'E2E-003', 'E2E-005', 'E2E-024', 'E2E-029', 'E2E-031', 'E2E-033',
    'E2E-059', 'E2E-064', 'E2E-085', 'E2E-109', 'E2E-125', 'E2E-160',
    'E2E-165', 'E2E-166',
  ],
};

const PU_MIGRATE = [
  [
    'PU-144-dsp', 'dsp', 'HTTP GET 企業編集(コード変更不可)',
    '本ケース専用の企業を登録済み', '編集画面を GET', 'GET /companies/{id}/edit',
    'レスポンス HTML に企業コード入力が readonly または disabled で企業名のみ編集可能',
    'layer:http, target:GET /companies/{id}/edit。旧 E2E-025 から移行', '画面項目 S-02a(企業コード)',
  ],
  [
    'PU-145-dsp', 'dsp', 'HTTP GET ユーザー編集(企業・ロール変更不可)',
    '本ケース専用のユーザーを作成済み', '編集画面を GET', 'GET /users/{id}/edit',
    'レスポンス HTML に所属企業とロールが readonly または disabled',
    'layer:http, target:GET /users/{id}/edit。旧 E2E-051 から移行', '画面項目 S-04a(企業/ロール)',
  ],
  [
    'PU-146-dsp', 'dsp', 'HTTP GET アンケート管理一覧(表示項目)',
    '本ケース専用の公開アンケート(回答 1 件)を作成済み', '一覧を GET', 'GET /surveys',
    'レスポンス HTML にタイトル・状態・締切・回答数・作成日の列見出しがあり回答数に 1 が含まれる',
    'layer:http, target:GET /surveys。旧 E2E-110 から移行', '画面項目 S-06(一覧)',
  ],
  [
    'PU-148-evt', 'evt', 'ユーザー部署変更と集計反映',
    '本ケース専用の企業に部署 営業部・総務部とユーザー(営業部)・回答 1 件を作成済み',
    'ユーザーの部署を総務部に PUT', 'PUT /users/{id} で department_id を変更',
    'DB の department_id が総務部になり SurveyResultService の部署=総務部絞り込みで件数 1 が返る',
    'layer:integration, target:SurveyResultService + PUT /users。旧 E2E-044 から移行', 'UC-14 AC-13',
  ],
  [
    'PU-149-auth', 'auth', 'HTTP GET /users/import（SU 企業ビュー）',
    '本ケース専用の企業を作成し SU で企業ビューに切替済み', 'URL 直接入力',
    'SU(企業ビュー)のセッションで GET /users/import を送信',
    '200 が返り CSV アップロードフォームが HTML に含まれる',
    'layer:http, target:GET /users/import。旧 E2E-165 から移行', '権限 ユーザー登録(CSV)×スーパーユーザー(企業ビュー)',
  ],
];

const oldE2eRows = parseCSV(readFileSync(join(SPEC, '03-test-plan.csv'), 'utf-8'));
const oldPuRows = parseCSV(readFileSync(join(SPEC, '03-test-plan-phpunit.csv'), 'utf-8'));

const header = oldE2eRows[0];
const e2eById = new Map();
for (const row of oldE2eRows.slice(1)) {
  e2eById.set(e2eId(row), row);
}

const newE2eRows = [header];
for (const id of [...KEEP_E2E].sort((a, b) => parseInt(a.slice(4), 10) - parseInt(b.slice(4), 10))) {
  const row = e2eById.get(id);
  if (!row) {
    console.error(`Missing E2E row: ${id}`);
    process.exit(1);
  }
  const copy = [...row];
  if (id === 'E2E-118') {
    copy[6] =
      '再度開くと「普通」が選択済みで表示され送信ボタンが「回答を修正する」表記になりダッシュボード集計が普通 1 件になる';
    copy[7] = (copy[7] ? copy[7] + '。' : '') + '旧 E2E-119 のボタン表記を統合';
  }
  newE2eRows.push(copy);
}

const newPuRows = [oldPuRows[0]];
const existingPu = new Set(oldPuRows.slice(1).map((r) => r[0].replace(/-(inp|evt|auth|trn|dsp|dyn|other)$/, '')));
for (const row of oldPuRows.slice(1)) {
  newPuRows.push(row);
}
for (const row of PU_MIGRATE) {
  const baseId = row[0].replace(/-(inp|evt|auth|trn|dsp|dyn|other)$/, '');
  if (!existingPu.has(baseId)) {
    newPuRows.push(row);
  }
}

writeFileSync(join(SPEC, '03-test-plan.csv'), newE2eRows.map(toCsvRow).join('\n') + '\n');
writeFileSync(join(SPEC, '03-test-plan-phpunit.csv'), newPuRows.map(toCsvRow).join('\n') + '\n');

const e2eN = newE2eRows.length - 1;
const puN = newPuRows.length - 1;
const vtN = parseCSV(readFileSync(join(SPEC, '03-test-plan-vitest.csv'), 'utf-8')).length - 1;
const total = e2eN + puN + vtN;

function catCount(rows) {
  const m = {};
  for (const row of rows.slice(1)) {
    const cat = (row[1] ?? '').split('-')[0] || row[1];
    m[cat] = (m[cat] ?? 0) + 1;
  }
  return m;
}

console.log('=== survey-system CSV v3 ===');
console.log(`E2E: ${e2eN} (was 60)`);
console.log(`PHPUnit: ${puN}`);
console.log(`Vitest: ${vtN}`);
console.log(`Total: ${total}`);
console.log(`E2E ratio: ${((e2eN / total) * 100).toFixed(1)}%`);
console.log('E2E by category:', catCount(newE2eRows));
console.log('Inventory:', {
  維持: INVENTORY.維持.length,
  移行: INVENTORY.移行.length,
  削減: INVENTORY.削減.length,
});
console.log('Removed E2E:', [...INVENTORY.移行, ...INVENTORY.削減].sort().join(', '));
console.log('New PHPUnit:', PU_MIGRATE.map((r) => r[0]).join(', '), '(E2E-080 → existing PU-015)');
