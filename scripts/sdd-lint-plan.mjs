#!/usr/bin/env node
/**
 * sdd-lint-plan.mjs — テスト計画 CSV の静的検証
 *
 * Usage: node scripts/sdd-lint-plan.mjs <slug>
 *        npm run lint:sdd -- csv-import
 *
 * 検証内容:
 *   1. Test ID が空でないこと
 *   2. 同一 CSV 内に Test ID の重複がないこと
 *   3. 出典（列 9）が空でないこと
 *   4. 期待結果（列 7）に曖昧語が含まれていないこと
 *   5. 出典列の ID 形式（UC-xx / VAL-xx / AC-xx / NFR-xx / IMPACT-xx）が
 *      01-requirements.md / 02-design.md に実在すること
 *   6. 03-test-plan.md の要件カバレッジ表:
 *        a. 対応 Test ID が空の行がないこと
 *        b. 要件 ID が 01-requirements.md / 02-design.md に実在すること
 *   7. 03-test-plan.md の回帰確認表に対応 Test ID が空の行がないこと
 *   8. テストピラミッド: §1.5 棚卸し、auth HTTP 残留、絞り込み E2E 重複、下層との観点重複の疑い
 *
 * Exit code: 1 (ERROR あり) / 0 (問題なし)
 */

import { readFileSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dirname, '..');

// 期待結果に含まれていた場合に警告する曖昧語パターン
const AMBIGUOUS_PATTERNS = [
  { re: /正しく(表示|保存|動作|処理|更新|登録|削除|実行)/, label: '「正しく〜」' },
  { re: /適切に/, label: '「適切に」' },
  { re: /問題なく/, label: '「問題なく」' },
  { re: /うまく/, label: '「うまく」' },
];

// 出典・カバレッジ表で使われる要件側 ID のパターン
const REQ_ID_RE = /\b((?:UC|VAL|AC|NFR|IMPACT)-\d+)\b/g;

// ----- 要件定義・設計書から定義済み ID を収集 -----
// Markdown テーブルの先頭列に現れる ID 形式（| UC-01 | など）を抽出する

function extractDefinedIds(filePath) {
  if (!existsSync(filePath)) return new Set();
  const content = readFileSync(filePath, 'utf-8');
  const ids = new Set();
  // テーブル行の先頭列: | <ID> | の形式
  const re = /\|\s*((?:UC|VAL|AC|NFR|IMPACT)-\d+)\s*\|/g;
  let m;
  while ((m = re.exec(content)) !== null) {
    ids.add(m[1]);
  }
  return ids;
}

// ----- CSV パーサ -----

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

function parseCSV(content) {
  return content
    .split('\n')
    .filter(l => l.trim().length > 0)
    .map(parseCSVLine);
}

// ----- CSV 検証 -----
// 列順: Test ID(0) カテゴリ(1) 対象(2) 前提条件(3) 入力/操作(4) 手順(5) 期待結果(6) 備考(7) 出典(8)

function lintCSVFile(filePath, relPath, definedIds) {
  if (!existsSync(filePath)) return [];

  const rows = parseCSV(readFileSync(filePath, 'utf-8'));
  if (rows.length === 0) return [];

  const issues = [];
  const seenIds = new Map();

  // ヘッダー行確認
  const header = rows[0];
  if (!header[0]?.includes('Test ID')) {
    issues.push(error(relPath, 1, 'ヘッダー行の先頭列が "Test ID" ではありません'));
    return issues;
  }

  for (let i = 1; i < rows.length; i++) {
    const row = rows[i];
    const lineNum = i + 1;
    const testId = (row[0] ?? '').trim();
    const expected = (row[6] ?? '').trim();
    const source = (row[8] ?? '').trim();

    if (!testId) {
      issues.push(error(relPath, lineNum, 'Test ID が空です'));
      continue;
    }

    if (seenIds.has(testId)) {
      issues.push(error(relPath, lineNum, `Test ID "${testId}" が重複しています（初出: ${seenIds.get(testId)} 行目）`));
    } else {
      seenIds.set(testId, lineNum);
    }

    if (!source) {
      issues.push(error(relPath, lineNum, `出典が空です（Test ID: ${testId}）`));
    } else {
      // 旧セクション参照形式（§X.Y）が残っていたら WARN
      if (/§\d/.test(source)) {
        issues.push(warn(relPath, lineNum,
          `出典に旧セクション参照形式（§X.Y）が含まれています。ID 形式（UC-xx / VAL-xx / AC-xx / NFR-xx 等）への更新を推奨します（Test ID: ${testId}）`));
      }
      // 出典列に ID 形式が含まれる場合、要件定義・設計書への実在を確認する
      if (definedIds && definedIds.size > 0) {
        const idRe = new RegExp(REQ_ID_RE.source, 'g');
        let m;
        while ((m = idRe.exec(source)) !== null) {
          const refId = m[1];
          if (!definedIds.has(refId)) {
            issues.push(error(relPath, lineNum,
              `出典の "${refId}" が要件定義・設計書に未定義です（Test ID: ${testId}）`));
          }
        }
      }
    }

    for (const { re, label } of AMBIGUOUS_PATTERNS) {
      if (re.test(expected)) {
        issues.push(warn(relPath, lineNum, `期待結果に曖昧語 ${label} が含まれている可能性があります（Test ID: ${testId}）`));
        break;
      }
    }
  }

  return issues;
}

// ----- Markdown カバレッジ表・回帰確認表 検証 -----

function lintMarkdownTables(mdPath, relPath, definedIds) {
  if (!existsSync(mdPath)) return [];

  const lines = readFileSync(mdPath, 'utf-8').split('\n');
  const issues = [];

  // 対象セクション定義:
  //   name: セクション見出し（前方一致）
  //   testIdCol: 「対応 Test ID」列の index（split('|') 後）
  //   skipValues: テンプレート例示行として無視する 要件ID 値
  const sections = [
    {
      name: '### 要件カバレッジ',
      testIdCol: 4,
      skipValues: ['（画面項目名）', '（ロール×操作）'],
    },
    {
      name: '### 回帰確認',
      testIdCol: 3,
      skipValues: ['IMPACT-01'],
    },
  ];

  let currentSection = null;
  let tableStarted = false;
  let headerSeen = false;

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    const lineNum = i + 1;

    // セクション切替
    if (line.startsWith('#')) {
      const matched = sections.find(s => line.startsWith(s.name));
      currentSection = matched ?? null;
      tableStarted = false;
      headerSeen = false;
      continue;
    }

    if (!currentSection) continue;

    if (!line.startsWith('|')) continue;

    // 区切り行（|---|...）
    if (line.includes('---')) {
      tableStarted = true;
      continue;
    }

    // ヘッダー行
    if (!headerSeen) {
      headerSeen = true;
      continue;
    }

    if (!tableStarted) continue;

    const cols = line.split('|').map(c => c.trim());
    const reqId = cols[1] ?? '';
    const testIds = cols[currentSection.testIdCol] ?? '';

    if (!reqId) continue;
    if (currentSection.skipValues.includes(reqId)) continue;

    // 要件カバレッジ表の 要件 ID が定義済みかを確認（ID 形式のみ対象）
    if (definedIds && definedIds.size > 0 && /^(UC|VAL|AC|NFR|IMPACT)-\d+$/.test(reqId)) {
      if (!definedIds.has(reqId)) {
        issues.push(
          error(relPath, lineNum,
            `要件カバレッジ表の "${reqId}" が要件定義・設計書に未定義です`)
        );
      }
    }

    if (!testIds) {
      issues.push(
        warn(relPath, lineNum,
          `${currentSection.name.replace('### ', '')} 表の "${reqId}" に対応 Test ID がありません`)
      );
    }
  }

  return issues;
}

// ----- テストピラミッド検証 -----

function countDataRows(csvPath) {
  if (!existsSync(csvPath)) return 0;
  const rows = parseCSV(readFileSync(csvPath, 'utf-8'));
  return Math.max(0, rows.length - 1);
}

function lintPyramid(specDir, slug) {
  const issues = [];
  const rel = (p) => `docs/specs/${slug}/${p}`;

  const e2eCount = countDataRows(join(specDir, '03-test-plan.csv'));
  const puCount = countDataRows(join(specDir, '03-test-plan-phpunit.csv'));
  const vtCount = countDataRows(join(specDir, '03-test-plan-vitest.csv'));
  const total = e2eCount + puCount + vtCount;

  if (total > 0 && e2eCount > 0) {
    const ratio = (e2eCount / total) * 100;
    const ratioLabel = `${e2eCount}/${total} = ${ratio.toFixed(1)}%`;
    // 件数比は上限ではない。高いときだけ重複確認のシグナルとして INFO 相当の WARN を出す
    if (ratio > 25) {
      issues.push(warn(rel('03-test-plan.csv'), null,
        `E2E 件数比が高めです（${ratioLabel}）。比率自体は不合格基準ではありません。§1.5 で下層との重複・移行漏れがないか確認してください`));
    }
  }

  const mdPath = join(specDir, '03-test-plan.md');
  if (existsSync(mdPath)) {
    const md = readFileSync(mdPath, 'utf-8');
    if (!/###\s+1\.5\s+E2E\s+棚卸し/.test(md)) {
      issues.push(warn(rel('03-test-plan.md'), null,
        '§1.5（E2E 棚卸し）がありません。testing-pyramid.mdc の作成手順に従って追加してください'));
    }
  }

  const e2ePath = join(specDir, '03-test-plan.csv');
  if (existsSync(e2ePath)) {
    const rows = parseCSV(readFileSync(e2ePath, 'utf-8'));
    const filterE2E = [];
    for (let i = 1; i < rows.length; i++) {
      const row = rows[i];
      const lineNum = i + 1;
      const testId = (row[0] ?? '').trim();
      const category = (row[1] ?? '').trim();
      const target = (row[2] ?? '').trim();
      const expected = (row[6] ?? '').trim();
      const note = (row[7] ?? '').trim();

      if (category === 'auth' && /403|404|Forbidden|Not Found|アクセス拒否|権限がありません/.test(expected + note)) {
        issues.push(warn(rel('03-test-plan.csv'), lineNum,
          `E2E auth に HTTP 認可（403/404）らしき期待結果があります。PHPUnit HTTP へ移行を検討（${testId}）`));
      }

      if (/絞り込み|フィルタ|filter/i.test(target + (row[4] ?? '') + note)) {
        filterE2E.push({ testId, lineNum });
      }
    }
    if (filterE2E.length > 1) {
      const ids = filterE2E.map((x) => x.testId).join(', ');
      issues.push(warn(rel('03-test-plan.csv'), filterE2E[1].lineNum,
        `絞り込み・フィルタ関連の E2E が ${filterE2E.length} 件あります（${ids}）。代表 1 件 + PHPUnit 網羅を推奨`));
    }
  }

  return issues;
}

// ----- 出力ヘルパー -----

function error(file, line, msg) { return { level: 'ERROR', file, line, msg }; }
function warn(file, line, msg)  { return { level: 'WARN',  file, line, msg }; }

function printIssue({ level, file, line, msg }) {
  const loc = line ? `${file}:${line}` : file;
  const tag = level === 'ERROR' ? '[ERROR]' : '[WARN] ';
  (level === 'ERROR' ? console.error : console.warn)(`${tag} ${loc} — ${msg}`);
}

// ----- エントリポイント -----

function main() {
  const slug = process.argv[2];
  if (!slug) {
    console.error('Usage: node scripts/sdd-lint-plan.mjs <slug>');
    console.error('       npm run lint:sdd -- csv-import');
    process.exit(1);
  }

  const specDir = join(ROOT, 'docs', 'specs', slug);
  if (!existsSync(specDir)) {
    console.error(`Error: docs/specs/${slug}/ が見つかりません`);
    process.exit(1);
  }

  const allIssues = [];

  // 01-requirements.md と 02-design.md から定義済み要件 ID を収集する
  const definedIds = new Set([
    ...extractDefinedIds(join(specDir, '01-requirements.md')),
    ...extractDefinedIds(join(specDir, '02-design.md')),
  ]);

  if (definedIds.size > 0) {
    console.log(`  定義済み要件 ID: ${[...definedIds].sort().join(', ')}`);
  }

  // CSV 3 種を検証
  for (const csv of ['03-test-plan.csv', '03-test-plan-phpunit.csv', '03-test-plan-vitest.csv']) {
    allIssues.push(...lintCSVFile(
      join(specDir, csv),
      `docs/specs/${slug}/${csv}`,
      definedIds
    ));
  }

  // MD のカバレッジ表・回帰確認表を検証
  allIssues.push(...lintMarkdownTables(
    join(specDir, '03-test-plan.md'),
    `docs/specs/${slug}/03-test-plan.md`,
    definedIds
  ));

  // テストピラミッド（件数比・棚卸し・auth HTTP 残留等）
  allIssues.push(...lintPyramid(specDir, slug));

  // 集計・出力
  let errorCount = 0;
  let warnCount  = 0;
  for (const issue of allIssues) {
    printIssue(issue);
    if (issue.level === 'ERROR') errorCount++; else warnCount++;
  }

  if (allIssues.length === 0) {
    console.log(`✓ [${slug}] テスト計画 lint OK（ERROR 0 / WARN 0）`);
  } else {
    console.log(`\n[${slug}] 合計: ERROR ${errorCount} 件 / WARN ${warnCount} 件`);
  }

  process.exit(errorCount > 0 ? 1 : 0);
}

main();
