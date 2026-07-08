#!/usr/bin/env node
/**
 * sdd-lint-testid.mjs — テスト計画 CSV とテストコードの Test ID 突合
 *
 * Usage: node scripts/sdd-lint-testid.mjs <slug>
 *        npm run lint:sdd:testid -- csv-import
 *
 * 検証内容:
 *   1. CSV の E2E Test ID が Playwright spec（.spec.ts）に存在するか（未実装検出 → ERROR）
 *   2. CSV の PHPUnit Test ID が PHPUnit テスト（.php）に存在するか（未実装検出 → ERROR）
 *   3. CSV の Vitest Test ID が Vitest テスト（.test.js）に存在するか（未実装検出 → ERROR）
 *   4. テストコードに CSV にない Test ID が存在するか（孤立検出 → WARN）
 *
 * Test ID アノテーション規約（テストコード側）:
 *   Playwright (.spec.ts): コメント行  // E2E-xxx-nnn: 説明
 *   Vitest     (.test.js): コメント行  // Vitest-xxx-nnn: 説明
 *   PHPUnit    (.php):     docコメント  * PHPUnit-xxx-nnn: 説明
 *
 * Exit code: 1 (ERROR あり) / 0 (問題なし)
 */

import { readFileSync, existsSync, readdirSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dirname, '..');

// Test ID のパターン: <Prefix>-<nnn>-<category>（例: E2E-001-trn / PU-001-auth / VT-001-dyn）
const TEST_ID_RE = /\b(E2E|PU|VT)-\d+-[a-zA-Z]+\b/g;

// ----- ファイル探索 -----

function walkDir(dir, filter) {
  if (!existsSync(dir)) return [];
  const results = [];
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const fullPath = join(dir, entry.name);
    if (entry.isDirectory()) {
      results.push(...walkDir(fullPath, filter));
    } else if (filter(entry.name)) {
      results.push(fullPath);
    }
  }
  return results;
}

// ----- Test ID 抽出 -----

function extractIdsFromFile(filePath) {
  const content = readFileSync(filePath, 'utf-8');
  const ids = new Set();
  let m;
  const re = new RegExp(TEST_ID_RE.source, 'g');
  while ((m = re.exec(content)) !== null) {
    ids.add(m[0]);
  }
  return ids;
}

function extractIdsFromDir(dir, fileFilter) {
  const files = walkDir(dir, fileFilter);
  const ids = new Set();
  for (const f of files) {
    for (const id of extractIdsFromFile(f)) {
      ids.add(id);
    }
  }
  return ids;
}

// ----- CSV から Test ID を収集 -----

function parseCSVLine(line) {
  const fields = [];
  let inQuote = false;
  let cur = '';
  for (const ch of line) {
    if (ch === '"') {
      inQuote = !inQuote;
    } else if (ch === ',' && !inQuote) {
      fields.push(cur);
      cur = '';
    } else {
      cur += ch;
    }
  }
  fields.push(cur);
  return fields;
}

function loadCSVIds(filePath) {
  if (!existsSync(filePath)) return new Set();
  const ids = new Set();
  const rows = readFileSync(filePath, 'utf-8')
    .split('\n')
    .filter(l => l.trim().length > 0)
    .map(parseCSVLine);
  for (let i = 1; i < rows.length; i++) {
    const id = rows[i][0]?.trim();
    if (id) ids.add(id);
  }
  return ids;
}

// ----- 出力ヘルパー -----

function printError(msg) { console.error(`[ERROR] ${msg}`); }
function printWarn(msg)  { console.warn(`[WARN]  ${msg}`); }

// ----- エントリポイント -----

function main() {
  const slug = process.argv[2];
  if (!slug) {
    console.error('Usage: node scripts/sdd-lint-testid.mjs <slug>');
    console.error('       npm run lint:sdd:testid -- csv-import');
    process.exit(1);
  }

  const specDir = join(ROOT, 'docs', 'specs', slug);
  if (!existsSync(specDir)) {
    console.error(`Error: docs/specs/${slug}/ が見つかりません`);
    process.exit(1);
  }

  // --- CSV から各 prefix の Test ID を収集 ---
  const csvE2E      = loadCSVIds(join(specDir, '03-test-plan.csv'));
  const csvPHPUnit  = loadCSVIds(join(specDir, '03-test-plan-phpunit.csv'));
  const csvVitest   = loadCSVIds(join(specDir, '03-test-plan-vitest.csv'));

  // --- テストコードから Test ID を収集 ---
  // Playwright: tests/e2e_tests/tests/*.spec.ts
  const codeE2E = extractIdsFromDir(
    join(ROOT, 'tests', 'e2e_tests', 'tests'),
    name => name.endsWith('.spec.ts')
  );
  // PHPUnit: tests/**/*.php
  const codePHPUnit = extractIdsFromDir(
    join(ROOT, 'tests'),
    name => name.endsWith('.php')
  );
  // Vitest: resources/js/**/*.test.js
  const codeVitest = extractIdsFromDir(
    join(ROOT, 'resources', 'js'),
    name => name.endsWith('.test.js')
  );

  let errorCount = 0;
  let warnCount  = 0;

  // --- 突合チェック ---
  const checks = [
    { label: 'E2E（Playwright）',  csvIds: csvE2E,     codeIds: codeE2E,     prefix: 'E2E' },
    { label: 'PHPUnit',            csvIds: csvPHPUnit,  codeIds: codePHPUnit, prefix: 'PU' },
    { label: 'Vitest',             csvIds: csvVitest,   codeIds: codeVitest,  prefix: 'VT' },
  ];

  for (const { label, csvIds, codeIds, prefix } of checks) {
    if (csvIds.size === 0 && codeIds.size === 0) continue;

    console.log(`\n── ${label} ──`);

    // 1. CSV にあるがコードにない（未実装）
    const notImplemented = [...csvIds].filter(id => !codeIds.has(id)).sort();
    for (const id of notImplemented) {
      printError(`未実装: Test ID "${id}" が CSV にありますがテストコードで見つかりません`);
      errorCount++;
    }

    // 2. コードにあるが CSV にない（孤立テスト）
    //    他の slug の CSV に存在する可能性があるため WARN 扱い
    const prefixRe = new RegExp(`^${prefix}-`);
    const orphaned = [...codeIds]
      .filter(id => prefixRe.test(id) && !csvIds.has(id))
      .sort();
    for (const id of orphaned) {
      printWarn(`孤立テスト: Test ID "${id}" がテストコードにありますが ${slug} の CSV に見つかりません（他 slug の CSV に存在する場合は無視可）`);
      warnCount++;
    }

    if (notImplemented.length === 0 && orphaned.length === 0) {
      console.log(`  ✓ CSV ${csvIds.size} 件 / コード ${[...codeIds].filter(id => prefixRe.test(id)).length} 件 — 一致`);
    }
  }

  // --- 集計 ---
  console.log(`\n[${slug}] 突合結果: ERROR ${errorCount} 件 / WARN ${warnCount} 件`);
  process.exit(errorCount > 0 ? 1 : 0);
}

main();
