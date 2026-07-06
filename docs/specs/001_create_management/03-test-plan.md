# テスト計画 — 社内備品貸出管理

> status: 03-test-plan.status を参照
> 前提: 02-design.status が `approved`

## 1. テスト方針

| 種別 | 対象 | ツール | 備考 |
|------|------|--------|------|
| 単体（サーバー） | `EquipmentLoanApplicationService`, `EquipmentLoanStatusService`, `EquipmentLoanQueryService`, `EquipmentLoanPresenter` | PHPUnit | 部署制限、重複期間、在庫数、権限、返却期限超過判定を確認 |
| 単体（フロント） | `equipmentLoanView.js` | Vitest | ステータスバッジ、期限切れアラート、注意アイコン、管理者ボタン表示の DOM 生成を確認 |
| E2E | `/equipment-loans` 画面、API 連携、権限、表示切替 | Playwright（TypeScript） | 主テスト。ユーザー操作として成立する受け入れ条件を確認 |

E2E を主テストとし、Service の複雑な業務ルールは PHPUnit で境界値を補完する。Vitest は DOM 生成ロジックを `equipmentLoanView.js` に切り出した場合に限定して実施する。

## 2. テスト種別別・カテゴリ別件数

CSV はテスト種別ごとに分割する。`03-test-plan.csv` は Playwright E2E 専用とし、PHPUnit / Vitest の単体テストは別 CSV に記載する。

| 種別 | CSV | 件数 | 主な確認内容 |
|------|-----|------|--------------|
| E2E | `03-test-plan.csv` | 49（うち 12 件は改修: 返却申請フロー追加） | 画面表示、ユーザー操作、API request による外部挙動、権限表示、エラー表示 |
| PHPUnit | `03-test-plan-phpunit.csv` | 38（うち 9 件は改修: 返却申請フロー追加） | Service / Form Request 相当の正常系・異常系、境界値、権限、レスポンス組み立て |
| Vitest | `03-test-plan-vitest.csv` | 22（うち 3 件は改修: 返却申請フロー追加） | DOM の正常描画・特殊表示、期限切れアラート、注意アイコン、API ラッパー |

### 改修: 返却申請フロー追加（2026-07-06）

承認済みの申請に対する一般ユーザーの返却申請、管理者による返却確定・差し戻し、重複期間・在庫数・
返却期限超過判定への `return_requested` の反映を対象にケースを追加した。追加ケースの備考欄は
「改修」を付記している。

| カテゴリ | 接尾辞 | 主な対象 |
|----------|--------|----------|
| 入力項目定義 | inp | 必須入力、日付境界、任意理由、ID の未指定・存在なし |
| 表示確認 | dsp | 一覧、ステータスバッジ、期限切れアラート、注意アイコン、検索結果 |
| 権限 | auth | 一般社員 / 管理者の表示・操作可否、API 直接呼び出し |
| イベント | evt | ユーザー切替、検索、新規申請、ステータス更新、エラー表示、再取得 |
| 画面遷移 | trn | `/equipment-loans` 初期表示 |
| 動的挙動 | dyn | 返却期限超過判定、重複期間、在庫数、部署制限 |
| その他 | other | Seeder、レスポンス形式、対象外確認 |

## 3. 網羅基準

- [x] 正常系（代表パターン）
- [x] 異常系（バリデーション、存在しない ID 等）
- [x] 境界値（同日貸出、過去日、重複期間、在庫上限、0件表示）
- [x] 権限パターン（一般社員 / 管理者の閲覧・更新可否）
- [x] 派生パターン（ユーザー切替、検索、期限切れ有無、ステータス別表示）
- [x] 更新系の空入力（下記「更新系の標準観点」を項目ごとに確認）
- [x] CSV の各行が 1 ケース 1 観点になっていること（対象項目・条件・期待結果が異なる場合は分割）

### CSV ケース粒度の標準観点

`03-test-plan.csv` は **1 行 1 観点** を原則とする。

| 分割が必要な例 | 分割方針 |
|----------------|----------|
| 複数の入力項目をまとめている | 入力項目ごとに別ケースにする |
| 複数のエラー理由をまとめている | エラー理由ごとに別ケースにする（例: 未指定、形式不正、存在なし） |
| 複数のロールや権限をまとめている | ロールや権限パターンごとに別ケースにする |
| 複数の状態・ステータスをまとめている | 期待結果や遷移が異なる場合は状態・ステータスごとに別ケースにする |

複数条件を 1 行にまとめるのは、同じ操作・同じ期待結果として同一の確認観点である場合に限る。

### 更新系の標準観点（編集機能がある場合は必須）

要件の画面項目定義（必須/任意）に照らして、**全項目**について以下を確認する。

| 項目種別 | 空にして更新した場合の期待動作 | テストケース |
|----------|-------------------------------|-------------|
| 必須項目 | バリデーションエラーとなり、DB は元の値のまま | 必ず作成 |
| 任意項目（空入力 OK） | **空値（null / 空文字）で上書き保存される**（「空なら元の値を維持」にしない） | 必ず作成 |

※「空なら元の値を維持する」仕様にしたい場合は、要件定義（01-requirements.md）に明記されていることを確認する。明記がなければ open-questions で確認する。

本機能の更新系はステータス更新のみ。`status` は必須であり、空または許可外値の場合は 422、DB は元の値のままとする。新規申請フォームの `reason` は任意項目であり、空の場合は `null` または空文字として保存され、元値維持の挙動は不要。

## 4. テストデータ

Seeder で以下を用意する。

| 種別 | データ | 用途 |
|------|--------|------|
| ユーザー | 山田 太郎（開発部, staff） | 初期選択ユーザー、一般社員正常系 |
| ユーザー | 佐藤 花子（総務部, staff） | 部署制限異常系、一般社員表示切替 |
| ユーザー | 管理 太郎（総務部, admin） | 管理者表示、ステータス更新 |
| 備品 | MacBook Pro M3（在庫 2, 開発部） | 開発部向け申請、部署制限、在庫数 |
| 備品 | iPhone 15 Pro（在庫 1, NULL） | 全部署向け申請 |
| 備品 | 検証用 Windows PC（在庫 1, 総務部） | 総務部向け申請、部署制限 |
| 申請 | 山田 太郎 / MacBook / `approved` / 返却期限超過 | 一般社員・管理者の期限切れアラート |
| 申請 | 佐藤 花子 / iPhone / `pending` / 未来日 | 管理者一覧、重複期間 |
| 申請 | 山田 太郎 / iPhone / `returned` / 過去日 | 期限切れ対象外確認 |
| 申請 | 山田 太郎 / 検索用備品 / 各ステータス | 検索・ステータスバッジ確認 |

改修（返却申請フロー追加）で必要な `return_requested` の申請データは、既存の重複期間・在庫数ケース
（`E2E-dyn-005` 等）と同様に **Seeder には追加せず**、各テスト内で API（`apiCreate` + `apiUpdateStatus`）
または SQL 直接投入（過去日など API で作成できない場合）により都度作成する。Seeder に恒久データを
追加すると、既存ケースが前提とする件数（山田 太郎 2 件、管理者視点 3 件など）が変わり、
既存の正常系アサーションに影響するため。

## 5. E2E 対応

Playwright を主テストとして、ユーザー操作で成立する受け入れ条件を確認する。画面表示、擬似ログイン切替、検索、新規申請、管理者によるステータス更新、返却期限超過アラートを対象とする。

API の存在しない ID や権限エラーは、画面操作で到達しにくいものに限り Playwright の API request で確認する。

| spec ファイル | CSV |
|--------------|-----|
| `tests/test_equipment_loans.spec.ts` | `03-test-plan.csv` |

## 6. Vitest 対応（該当時）

Vitest は JavaScript の単体テストに限定する。画面全体のユーザー操作、API 連携、権限切替は Playwright E2E で確認する。

| テストファイル | 対象 JS | 概要 |
|--------------|---------|------|
| `resources/js/equipmentLoanView.test.js` | `resources/js/equipmentLoanView.js` | ステータスバッジ、期限切れアラート、注意アイコン、管理者ボタン表示。詳細は `03-test-plan-vitest.csv` |
| `resources/js/equipmentLoanApi.test.js` | `resources/js/equipmentLoanApi.js` | API ラッパーの URL / パラメータ組み立て。詳細は `03-test-plan-vitest.csv` |

## 7. PHPUnit 対応

PHPUnit は Service と Form Request 相当の業務ルール確認に限定する。E2E では確認しづらい境界値、例外、DB が変更されないこと、返却期限超過判定、ロール別の権限を単体で補完する。

Controller の機能テストを E2E の代替として広げることはしない。

| テストファイル | 対象 | 概要 |
|--------------|------|------|
| `tests/Unit/EquipmentLoanApplicationServiceTest.php` | `EquipmentLoanApplicationService` | 部署制限、重複期間、在庫数、同日貸出、過去日補助条件。詳細は `03-test-plan-phpunit.csv` |
| `tests/Unit/EquipmentLoanStatusServiceTest.php` | `EquipmentLoanStatusService` | 管理者更新可、一般社員更新不可、許可ステータス。詳細は `03-test-plan-phpunit.csv` |
| `tests/Unit/EquipmentLoanQueryServiceTest.php` | `EquipmentLoanQueryService` | 一般社員 / 管理者の一覧範囲、検索、Eager Loading 前提、`can_request_return` 判定（改修）。詳細は `03-test-plan-phpunit.csv` |
| `tests/Unit/EquipmentLoanPresenterTest.php` | `EquipmentLoanPresenter` | 返却期限超過（`return_requested` を含む、改修）、アラートサマリ、一覧行レスポンス。詳細は `03-test-plan-phpunit.csv` |

## 8. 詳細ケース

詳細ケースはテスト種別ごとの CSV を参照する。

| 種別 | CSV |
|------|-----|
| E2E | `03-test-plan.csv` |
| PHPUnit | `03-test-plan-phpunit.csv` |
| Vitest | `03-test-plan-vitest.csv` |
