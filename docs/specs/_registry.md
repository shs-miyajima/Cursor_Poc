# 機能レジストリ

`docs/specs/<slug>/` と E2E テスト資産の対応表。

| slug | display_name | feature_id | spec ファイル | 備考 |
|------|-------------|------------|--------------|------|
| （例）example-feature | （機能名） | TBD | tests/test_example.spec.ts | |
| sample-crud | サンプル CRUD（トップ画面） | TBD | tests/test_sample_crud.spec.ts | **spec フォルダ・spec ファイルとも未作成**（SDD 成果物なし）。実装着手時に `_templates/` からフォルダを作成すること。E2E は環境制約により実行保留 |
| survey-system | アンケート作成・回答・管理システム | TBD | tests/test_survey_*.spec.ts（10 ファイル、03-test-plan.md §5 参照） | フェーズ 3 承認済み・フェーズ 4（実装・テスト）進行中 |

## 追記ルール

- 新機能開始時（`_templates/` を `<slug>/` にコピーした直後）に行を追加する
- `feature_id` は命名規則確定まで `TBD` とする
- E2E 実装後に `spec ファイル` を更新する
