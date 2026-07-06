# 機能レジストリ

`docs/specs/<slug>/` と E2E テスト資産の対応表。

| slug | display_name | feature_id | spec ファイル | 備考 |
|------|-------------|------------|--------------|------|
| （例）example-feature | （機能名） | TBD | tests/test_example.spec.ts | |
| sample-crud | サンプル CRUD（トップ画面） | TBD | tests/test_sample_crud.spec.ts | E2E は環境制約により実行保留 |
| 001_create_management | 社内備品貸出管理 | TBD | tests/test_equipment_loans.spec.ts | 実装済み。E2E は環境制約により実行保留 |

## 追記ルール

- 新機能開始時（`_templates/` を `<slug>/` にコピーした直後）に行を追加する
- `feature_id` は命名規則確定まで `TBD` とする
- E2E 実装後に `spec ファイル` を更新する
