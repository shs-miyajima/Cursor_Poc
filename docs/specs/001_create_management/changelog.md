# 変更履歴

## 2026-07-06

- フェーズ: —（記録）
- 操作: draft
- 内容: 工数レポートの仕組みを導入し、`effort-report.md` にチャット履歴から遡って人手想定工数（50h）と実績（1h 34m）を記録。削減率約 97%。

## 2026-07-06

- フェーズ: 実装・テスト
- 操作: draft
- 内容: フェーズ 4 実装完了。Migration / Model / Enum / Service / Form Request / Controller / Route / Blade / JS / Seeder を実装し、migrate + seed + 画面/API 疎通を確認。PHPUnit 31 件パス（テスト計画 29 ケース + 既存サンプル 2 件）、Vitest 20 件パス（テスト計画 19 ケース + 既存サンプル 1 件）。Playwright E2E は 37 ケース分のテストコードを tests/e2e_tests/tests/test_equipment_loans.spec.ts に作成したが、環境制約（社内 SSL によりブラウザ取得不可）のため実行はスキップ（ユーザー了承済み）。環境解決後に実行する。

## 2026-07-06

- フェーズ: テスト設計
- 操作: approved
- 内容: ユーザーの明示的な承認（選択式確認で「承認する」を選択）により `03-test-plan.status` を approved に更新。フェーズ 4（実装・テスト）に着手する。

## 2026-07-06

- フェーズ: テスト設計
- 操作: draft
- 内容: E2E に管理者の却下操作と対象部署 NULL 備品の申請正常系を追加し、承認・却下を個別ケースに分割。PHPUnit にも部署制限なし（NULL）の正常系を追加。ワークフロールールとテンプレートに「許可操作の全列挙」「条件分岐の全値確認」の観点を追加。

## 2026-07-06

- フェーズ: テスト設計
- 操作: draft
- 内容: Vitest 単体テストに通常一覧行、空一覧、エラー表示、ステータス別バッジ、API ラッパー正常系・エラー伝播を追加。Vitest 規約にも正常系確認のルールを追記。

## 2026-07-06

- フェーズ: テスト設計
- 操作: draft
- 内容: PHPUnit 単体テストに正常に通る代表ケースを追加し、異常系だけに偏らないよう修正。今後のテンプレートとワークフロールールにも OK / NG ケースを対で確認する観点を追加。

## 2026-07-06

- フェーズ: テスト設計
- 操作: draft
- 内容: 今後の SDD テスト設計で CSV をテスト種別ごとに分けるよう、ワークフロールール、テンプレート、README、SDD スキルを更新。

## 2026-07-06

- フェーズ: テスト設計
- 操作: draft
- 内容: Markdown の種別別分割を取りやめ、`03-test-plan.md` を全体方針・E2E / PHPUnit / Vitest 対応を含む単一ファイル構成に戻した。CSV の種別別分割は維持。

## 2026-07-06

- フェーズ: テスト設計
- 操作: draft
- 内容: Markdown もテスト種別ごとに分割し、`03-test-plan-e2e.md`、`03-test-plan-phpunit.md`、`03-test-plan-vitest.md` を追加。`03-test-plan.md` は全体方針と索引に整理。

## 2026-07-06

- フェーズ: テスト設計
- 操作: draft
- 内容: E2E と単体テストが混在しないよう、`03-test-plan.csv` を Playwright E2E 専用に整理し、`03-test-plan-phpunit.csv` と `03-test-plan-vitest.csv` を追加。`03-test-plan.md` の件数・対応表も分割後の構成へ更新。

## 2026-07-06

- フェーズ: テスト設計
- 操作: draft
- 内容: `03-test-plan.md` の CSV ケース粒度の標準観点から機能固有名詞を外し、入力項目・エラー理由・ロール・状態単位で分割する汎用表現に変更。

## 2026-07-06

- フェーズ: テスト設計
- 操作: draft
- 内容: 今後のテストケース作成で対象項目・条件・期待結果が異なる観点を 1 行にまとめないよう、`03-test-plan.md` に CSV ケース粒度の標準観点を追加。

## 2026-07-06

- フェーズ: テスト設計
- 操作: draft
- 内容: `mock_user_id` と `equipment_id` の未指定・存在なしを個別に検証できるよう、`03-test-plan.csv` の入力項目ケースを分割。

## 2026-07-06

- フェーズ: テスト設計
- 操作: draft
- 内容: `03-test-plan.md` と `03-test-plan.csv` に、Playwright E2E を主としたテスト方針、PHPUnit / Vitest の補完範囲、正常系・異常系・境界値・権限・返却期限超過・更新系の詳細ケースを作成。

## 2026-07-06

- フェーズ: 設計
- 操作: approved
- 内容: フェーズ 2 設計をユーザー承認により approved へ更新。

## 2026-07-06

- フェーズ: 設計
- 操作: draft
- 内容: `02-design.md` に Controller / Service / Model / Migration / Blade / JavaScript / API レスポンス設計を作成。返却期限超過アラートは `/equipment-loans` 画面上部と一覧行アイコンで表示する設計に整理。

## 2026-07-06

- フェーズ: 仕様整理
- 操作: approved
- 内容: 返却期限超過アラートを含むフェーズ 1 要件定義をユーザー承認により approved へ更新。

## 2026-07-06

- フェーズ: 仕様整理
- 操作: draft
- 内容: 返却期限超過（`status = approved` かつ `requested_to` が本日より前）の申請を TOP 画面上部のアラートと一覧行の注意アイコンで表示する要件を追加。

## 2026-07-06

- フェーズ: 仕様整理
- 操作: draft
- 内容: `001_create_management` を SDD テンプレート構成で作成し、外部仕様 `specification.md` を原典として `01-requirements.md` に取り込み。環境前提は既存 Docker 構成（Laravel 12 / PostgreSQL 18）を正とする方針を明記。
