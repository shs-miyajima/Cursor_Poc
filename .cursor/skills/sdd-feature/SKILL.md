---
name: sdd-feature
description: >-
  Cursor_Poc の仕様駆動開発（SDD）で新機能を追加する。仕様整理・設計・テスト設計・実装の
  各フェーズを docs/specs/ の成果物と承認ゲートで進める。機能追加・仕様駆動・SDD と
  言及されたときに使用する。
---

# 仕様駆動開発 — 機能追加

> 手順の正本は `.cursor/rules/sdd-workflow.mdc`。本 Skill と食い違う場合はルールを優先する。

## 開始手順

1. `docs/specs/_templates/` を `docs/specs/<slug>/` にコピー
2. `meta.yaml` の `display_name` と `slug` を設定
3. `docs/specs/_registry.md` に行を追加（feature_id は TBD 可）
4. 仕様（チャット・Markdown）を読み、フェーズ 1 から開始

## フェーズチェックリスト

### フェーズ 1: 仕様整理

- [ ] `01-requirements.md` を作成・更新
- [ ] `effort-report.md` の「§1 人手想定工数」にフェーズ別見積を記入（根拠つき）
- [ ] 不明点を `open-questions.md` に記載し、ユーザーに質問
- [ ] Laravel / フロント（Blade / Vite / JS）の責務分界を記載
- [ ] バリデーション（§7: VAL-xx）・受け入れ条件（§11: AC-xx）・非機能要件（§9: NFR-xx）に一意な ID を付与
- [ ] テンプレート §13「承認依頼前セルフチェック」を全項目確認
- [ ] 確定後、ユーザー承認を得て `01-requirements.status` を `approved` に

**停止**: 承認までフェーズ 2 に進まない

### フェーズ 2: 設計

- [ ] `01-requirements.status` が `approved` であることを確認
- [ ] `02-design.md` に以下を記載:
  - 新規・変更クラス（Controller / Service / Model / Job 等）
  - メソッド概要（シグネチャレベル）
  - DB 変更（migration）
  - 画面・ルート・フロント（Blade / JS）
- [ ] §5 影響範囲に、既存の画面・API・共通部品・テーブルへの波及を `IMPACT-xx` 付きで洗い出す
      （影響なしの判断も理由を明記する）
- [ ] **承認確認を提示する前に**、専用レビューサブエージェント `sdd-design-reviewer`
      （定義: `.cursor/agents/sdd-design-reviewer.md`、readonly、新規セッション）を起動し、
      `02-design-review-checklist.md` の全項目を検証させる（影響範囲はコードベース検索で裏取り）
- [ ] 指摘のうち機械的に修正可能なものはその場で修正し、`02-design-review-checklist.md` に結果を記録する
- [ ] 設計方針の変更を伴う指摘は自動修正せず、承認確認に含めて人間に提示する
- [ ] 承認後 `02-design.status` を `approved` に

**停止**: 承認までフェーズ 3 に進まない

### フェーズ 3: テスト設計

- [ ] `02-design.status` が `approved` であることを確認
- [ ] `.cursor/rules/testing-pyramid.mdc` に従い `03-test-plan.md` §1（ピラミッド・複合 VAL 分解表・閾値一覧）を記載
- [ ] **PHPUnit / Vitest を先に設計**し、E2E 候補を §1.5（棚卸し: 維持/移行/削減）で分類してから E2E CSV を確定する
- [ ] `03-test-plan.md` にテスト方針・カテゴリ別件数・§1.5 棚卸しを記載
- [ ] テスト種別ごとに CSV を分けてケース一覧を記載:
  - Playwright E2E: `03-test-plan.csv`
  - PHPUnit: `03-test-plan-phpunit.csv`（Service 等の単体テストがある場合）
  - Vitest: `03-test-plan-vitest.csv`（JavaScript 単体テストがある場合）
- [ ] 異なるテスト種別のケースを同じ CSV に混在させない
- [ ] 各 CSV は 1 行 1 観点で、正常系・異常系・境界値・権限・派生パターンを網羅する
- [ ] `03-test-plan.md` に要件カバレッジ表を作成し、`01-requirements.md` の UC / VAL / AC / NFR /
      画面項目 / 権限の全項目に対応する Test ID が存在することを確認する（NFR で対応不可のものは理由を明記）
- [ ] `02-design.md` §5 の `IMPACT-xx`（影響あり）に対応する回帰確認ケースを作成する
- [ ] 期待結果が曖昧な表現（「正しく」「適切に」等）になっていないか確認する
- [ ] 各テストケースが他ケースの実行結果・順序に依存せず、並列実行でも干渉しないか確認する
- [ ] **承認確認を提示する前に** `npm run lint:sdd -- <slug>` を実行し、ERROR をすべて修正する
- [ ] **承認確認を提示する前に**、専用レビューサブエージェント `sdd-plan-reviewer`
      （定義: `.cursor/agents/sdd-plan-reviewer.md`、readonly、新規セッション）を起動し、
      `03-test-plan-review-checklist.md` の全項目を検証させる
- [ ] 指摘のうち機械的に修正可能なものはその場で修正し、`03-test-plan-review-checklist.md` に結果を記録する
- [ ] 業務ルールの解釈等、判断が必要な指摘は自動修正せず承認確認に含めて人間に提示する
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
- [ ] `npm run lint:sdd:testid -- <slug>` を実行し、CSV とテストコードの Test ID が全件一致することを
      確認する（ERROR = 未実装ケースあり → テスト追加後に再実行して 0 件を確認してから次へ進む）
- [ ] E2E が環境制約で実行不能な場合は「未実行・理由・環境解決後に実行」を完了報告に明記する（実行できたことにしない）
- [ ] `04-completion-report.md` を作成（テスト実行結果・Test ID 突合・エビデンスパス・基準未達の理由）
- [ ] 完了時に `effort-report.md` の「§2 実績記録」「§3 削減効果」を確定し、完了報告に含める
- [ ] 実装・テストコード・完了報告を git commit する（`[SDD][<slug>] フェーズ4(実装・テスト) 完了`）

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
| PHPUnit 規約 | `.cursor/rules/testing-phpunit.mdc` |
| Vitest 規約 | `.cursor/rules/testing-vitest.mdc` |
| テストピラミッド | `.cursor/rules/testing-pyramid.mdc` |

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

## YYYY-MM-DD
- フェーズ: 仕様整理
- 操作: reopened
- 理由: 承認後に○○の要件が変更になったため draft に戻して差分承認
```
