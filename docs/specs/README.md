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

手順の正本は `.cursor/rules/sdd-workflow.mdc` です。本 README と食い違う場合はルールを優先します。

| フェーズ | 成果物 | 承認ファイル |
|---------|--------|-------------|
| 1. 仕様整理 | `01-requirements.md`, `open-questions.md`, `effort-report.md`（見積） | `01-requirements.status` |
| 2. 設計 | `02-design.md`, `02-design-review-checklist.md`（AI 独立レビュー結果） | `02-design.status` |
| 3. テスト設計 | `03-test-plan.md`, `03-test-plan.csv`, `03-test-plan-phpunit.csv`（該当時）, `03-test-plan-vitest.csv`（該当時）, `03-test-plan-review-checklist.md`（AI 独立レビュー結果） | `03-test-plan.status` |
| 4. 実装・テスト | コード・テストコード, `04-completion-report.md` | — |

`*.status` の値: `draft` | `approved` | `rejected`

status ファイルは 1 行目に状態、2 行目以降に `date:`（更新日）と `phase:` を記録します。

各フェーズで承認（`approved`）を得てから次へ進みます。承認できるのは人間のみで、
エージェントは直近のユーザー発言に明示的な承認がある場合に限り `approved` へ更新します。
承認・差戻しのたびに `changelog.md` へ記録します。承認済みの成果物を変更する場合は
差分承認（`draft` に戻して再承認）の手順に従います。

フェーズ 2（設計）とフェーズ 3（テスト設計）では、承認確認の前に読み取り専用
サブエージェントによる **AI 独立レビュー**を行い、結果を各レビューチェックリストに残します
（人間の承認を代替するものではありません）。

各フェーズの承認直後には、成果物と `*.status`・`changelog.md` をまとめて
git commit します（**フェーズコミット**。メッセージ形式:
`[SDD][<slug>] フェーズN(<フェーズ名>) approved`）。

テスト設計の CSV はテスト種別ごとに分けます。Playwright E2E は `03-test-plan.csv`、
PHPUnit は `03-test-plan-phpunit.csv`、Vitest は `03-test-plan-vitest.csv` を使用し、
異なるテスト種別のケースを同じ CSV に混在させません。

**レイヤ分担**（Playwright = ジャーニー＋クリティカル異常、PHPUnit/Vitest = ロジック・境界値・異常系網羅）
は `.cursor/rules/testing-pyramid.mdc` を正とします。

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
