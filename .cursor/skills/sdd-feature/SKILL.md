---
name: sdd-feature
description: >-
  Cursor_Poc の仕様駆動開発（SDD）で新機能を追加する。仕様整理・設計・テスト設計・実装の
  各フェーズを docs/specs/ の成果物と承認ゲートで進める。機能追加・仕様駆動・SDD と
  言及されたときに使用する。
---

# 仕様駆動開発 — 機能追加

## 開始手順

1. `docs/specs/_templates/` を `docs/specs/<slug>/` にコピー
2. `meta.yaml` の `display_name` と `slug` を設定
3. `docs/specs/_registry.md` に行を追加（feature_id は TBD 可）
4. 仕様（チャット・Markdown）を読み、フェーズ 1 から開始

## フェーズチェックリスト

### フェーズ 1: 仕様整理

- [ ] `01-requirements.md` を作成・更新
- [ ] 不明点を `open-questions.md` に記載し、ユーザーに質問
- [ ] Laravel / フロント（Blade / Vite / JS）の責務分界を記載
- [ ] 確定後、ユーザー承認を得て `01-requirements.status` を `approved` に

**停止**: 承認までフェーズ 2 に進まない

### フェーズ 2: 設計

- [ ] `01-requirements.status` が `approved` であることを確認
- [ ] `02-design.md` に以下を記載:
  - 新規・変更クラス（Controller / Service / Model / Job 等）
  - メソッド概要（シグネチャレベル）
  - DB 変更（migration）
  - 画面・ルート・フロント（Blade / JS）
- [ ] §5 影響範囲に、既存の画面・API・共通部品・テーブルへの波及を洗い出す
      （影響なしの判断も理由を明記する）
- [ ] **承認確認を提示する前に**、独立レビュー用サブエージェント（Task ツール・
      `subagent_type: generalPurpose`・`readonly: true`、新規セッション）を起動し、
      `02-design-review-checklist.md` の全項目を検証させる（影響範囲はコードベース検索で裏取り）
- [ ] 指摘のうち機械的に修正可能なものはその場で修正し、`02-design-review-checklist.md` に結果を記録する
- [ ] 設計方針の変更を伴う指摘は自動修正せず、承認確認に含めて人間に提示する
- [ ] 承認後 `02-design.status` を `approved` に

**停止**: 承認までフェーズ 3 に進まない

### フェーズ 3: テスト設計

- [ ] `02-design.status` が `approved` であることを確認
- [ ] `03-test-plan.md` にテスト方針・カテゴリ別件数を記載
- [ ] `03-test-plan.csv` にケース一覧（正常系・異常系・境界値・権限・派生パターン）
- [ ] 承認後 `03-test-plan.status` を `approved` に

**停止**: 承認までコード編集・テスト実行に進まない

### フェーズ 4: 実装・テスト

- [ ] `03-test-plan.status` が `approved` であることを確認
- [ ] Laravel 実装（リポジトリ直下）
- [ ] フロント実装（Blade / Vite / JavaScript / Tailwind）
- [ ] PHPUnit（Service 単体、該当時）
- [ ] Vitest（JS 単体、該当時）
- [ ] Playwright E2E（`tests/e2e_tests/`）
- [ ] 失敗時は原因分析 → 修正（最大 3 回）→ エスカレーション
- [ ] 完了時に実装・テストコードを git commit する（`[SDD][<slug>] フェーズ4(実装・テスト) 完了`）

## 承認の受け方

- 各フェーズの成果物が完成したら、**選択式の承認確認（承認 / 差戻し（理由入力））を提示して停止する**
- **直近のユーザー発言に明示的な承認がある場合のみ** `*.status` を `approved` に更新する（自己判断での承認は禁止）
- 「OK」など対象フェーズが曖昧な場合は、どのフェーズへの承認かを確認してから更新する
- 承認時: `*.status` を更新（1 行目 `approved`、`date:`・`phase:` を記録）+ `changelog.md` に 1 エントリ
- 差戻し時: `*.status` を `rejected` に + `changelog.md` に理由を記録
- **承認直後に git commit する**（`[SDD][<slug>] フェーズN(<フェーズ名>) approved`）。
  フェーズ 4 は完了時にコミット（`[SDD][<slug>] フェーズ4(実装・テスト) 完了`）。
  詳細は `.cursor/rules/sdd-workflow.mdc`「Git 運用（フェーズコミット）」

## 参照

| リソース | パス |
|---------|------|
| ワークフロー Rule | `.cursor/rules/sdd-workflow.mdc` |
| Laravel 規約 | `.cursor/rules/laravel-conventions.mdc` |
| フロント規約 | `.cursor/rules/frontend-vite-tailwind.mdc` |
| Playwright 規約 | `.cursor/rules/testing-playwright.mdc` |
| Vitest 規約 | `.cursor/rules/testing-vitest.mdc` |

## テスト実行コマンド

```bash
# PHPUnit（Docker コンテナ内）
docker compose exec app php artisan test
# または
docker compose exec app vendor/bin/phpunit --filter <TestClass>

# Vitest（ホスト、プロジェクトルート）
npm run test

# Playwright（ホスト、tests/e2e_tests ディレクトリ）
# 事前に run_debug.bat verify で Laravel の起動を確認する
cd tests/e2e_tests
npx playwright test tests/test_<name>.spec.ts
```

## changelog.md の書き方

承認・差戻しのたびに記録する。

```markdown
## YYYY-MM-DD
- フェーズ: 仕様整理
- 操作: approved

## YYYY-MM-DD
- フェーズ: 設計
- 操作: rejected
- 理由: ○○の責務分界が不明
- 対応: 02-design.md の §3 を修正
```
