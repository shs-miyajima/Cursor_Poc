#!/usr/bin/env node
/**
 * survey-system E2E spec を 40 件計画に合わせて整理する。
 * Usage: node scripts/trim-e2e-survey-40.mjs
 */
import { readFileSync, writeFileSync, unlinkSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const E2E_DIR = join(__dirname, '..', 'tests', 'e2e_tests', 'tests');

const KEEP = new Set([
  'E2E-001-trn', 'E2E-002-trn', 'E2E-004-auth', 'E2E-006-inp', 'E2E-008-trn', 'E2E-009-auth',
  'E2E-011-evt', 'E2E-012-inp', 'E2E-021-evt', 'E2E-022-inp', 'E2E-023-evt', 'E2E-026-dsp', 'E2E-027-evt',
  'E2E-030-evt', 'E2E-032-evt', 'E2E-034-inp', 'E2E-045-inp', 'E2E-052-evt', 'E2E-164-auth',
  'E2E-054-evt', 'E2E-055-inp', 'E2E-060-inp', 'E2E-061-evt',
  'E2E-062-evt', 'E2E-066-inp',
  'E2E-084-evt', 'E2E-086-evt', 'E2E-087-evt', 'E2E-088-evt', 'E2E-090-evt', 'E2E-091-inp', 'E2E-108-dsp', 'E2E-170-inp',
  'E2E-117-evt', 'E2E-118-evt', 'E2E-120-inp', 'E2E-126-dsp', 'E2E-128-auth',
  'E2E-129-dsp', 'E2E-131-evt',
]);

const RENAME = {
  'E2E-004-inp': 'E2E-004-auth',
  'E2E-011-inp': 'E2E-011-evt',
  'E2E-030-inp': 'E2E-030-evt',
  'E2E-032-inp': 'E2E-032-evt',
  'E2E-054-inp': 'E2E-054-evt',
};

const SPEC_FILES = [
  'test_survey_auth.spec.ts',
  'test_survey_company.spec.ts',
  'test_survey_user.spec.ts',
  'test_survey_department.spec.ts',
  'test_survey_csv_import.spec.ts',
  'test_survey_manage.spec.ts',
  'test_survey_answer.spec.ts',
  'test_survey_dashboard.spec.ts',
];

const BLOCK_RE = /test\('(E2E-\d+-[a-z]+)[\s\S]*?\n\}\);/g;

function processFile(name) {
  const path = join(E2E_DIR, name);
  const content = readFileSync(path, 'utf-8');
  const firstTest = content.search(/test\('E2E-/);
  const header = firstTest > 0 ? content.slice(0, firstTest).trimEnd() : '';

  const kept = [];
  let total = 0;
  let m;
  const re = new RegExp(BLOCK_RE.source, 'g');
  while ((m = re.exec(content)) !== null) {
    total += 1;
    let id = m[1];
    let block = m[0];
    if (RENAME[id]) {
      block = block.replace(id, RENAME[id]);
      id = RENAME[id];
    }
    if (KEEP.has(id)) {
      kept.push(block);
    }
  }

  writeFileSync(path, kept.length > 0 ? `${header}\n\n${kept.join('\n\n')}\n` : `${header}\n`, 'utf-8');
  console.log(`${name}: kept ${kept.length} / ${total}`);
}

for (const f of SPEC_FILES) {
  processFile(f);
}

for (const obsolete of ['test_survey_authorization.spec.ts', 'test_survey_regression.spec.ts']) {
  const p = join(E2E_DIR, obsolete);
  if (existsSync(p)) {
    unlinkSync(p);
    console.log(`deleted ${obsolete}`);
  }
}

console.log('Done.');
