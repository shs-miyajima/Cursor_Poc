# 仕様（Specs）

本ディレクトリは **仕様駆動開発（SDD）の正本** です。実装・テストの判断はここを優先します。

仕様は **Markdown** で管理します。

## ディレクトリ構成

```
docs/specs/
  _templates/          # 新機能開始時にコピーするテンプレート
  _registry.md         # slug と E2E 資産の対応表
  <slug>/              # 機能ごとの SDD 成果物
```

## 新機能の開始手順

チャットで `/sdd-new <slug> <機能の説明>` を実行すると、以下が自動で行われます。

1. `_templates/` を `<slug>/` にコピー（slug は英小文字・ハイフン推奨）
2. `meta.yaml` の `display_name` を設定
3. `_registry.md` に行を追加
4. フェーズ 1（仕様整理）を開始し、不明点を質問して停止

進捗の確認は `/sdd-status`（全機能のフェーズ状況を一覧表示）。

## フェーズと承認

| フェーズ | 成果物 | 承認ファイル |
|---------|--------|-------------|
| 1. 仕様整理 | `01-requirements.md` | `01-requirements.status` |
| 2. 設計 | `02-design.md` | `02-design.status` |
| 3. テスト設計 | `03-test-plan.md`, `03-test-plan.csv`, `03-test-plan-phpunit.csv`（該当時）, `03-test-plan-vitest.csv`（該当時）, `03-test-plan-review-checklist.md`（AI 独立レビュー結果） | `03-test-plan.status` |
| 4. 実装 | コード・テスト, `04-completion-report.md` | — |

`*.status` の値: `draft` | `approved` | `rejected`

status ファイルは 1 行目に状態、2 行目以降に `date:`（更新日）と `phase:` を記録します。

各フェーズで承認（`approved`）を得てから次へ進みます。承認できるのは人間のみで、
エージェントは直近のユーザー発言に明示的な承認がある場合に限り `approved` へ更新します。
承認・差戻しのたびに `changelog.md` へ記録します。

テスト設計の CSV はテスト種別ごとに分けます。Playwright E2E は `03-test-plan.csv`、
PHPUnit は `03-test-plan-phpunit.csv`、Vitest は `03-test-plan-vitest.csv` を使用し、
異なるテスト種別のケースを同じ CSV に混在させません。

テスト設計フェーズは、承認確認を提示する前に**独立したレビュー用のサブエージェント**が
`03-test-plan-review-checklist.md` に沿って検証します（詳細: `.cursor/rules/sdd-workflow.mdc`
「独立レビュー」）。機械的に自動修正可能な指摘はその場で修正し、判断が必要な指摘のみ
承認確認と一緒に人間に提示します。この独立レビューは人間の承認を代替しません。

## 工数レポート

各機能の `effort-report.md`（テンプレート: `_templates/effort-report.md`）に、
人手作業した場合の見積工数と SDD での実績時間を記録し、工数削減効果を確認します。

- フェーズ 1 開始時: 人手想定工数（フェーズ別・根拠つき）を記入
- 各フェーズ承認時: 実績（着手・承認時刻、経過時間、差戻し回数）を記入
- フェーズ 4 完了時: 削減時間・削減率を算出

工数レポートは記録用で、承認ゲートの対象外です。

## 機能 ID について

命名規則は **未定（TBD）** です。`meta.yaml` の `feature_id` は `TBD` のままにできます。フォルダ名は `<slug>` を使用します。

## 関連設定

- Cursor Rules: `.cursor/rules/sdd-workflow.mdc` 他
- Cursor Skills: `.cursor/skills/sdd-bootstrap/SKILL.md`, `.cursor/skills/sdd-feature/SKILL.md`
- エージェント概要: リポジトリ直下の `AGENTS.md`
