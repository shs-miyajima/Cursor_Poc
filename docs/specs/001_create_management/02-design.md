# 設計 — 社内備品貸出管理

> status: 02-design.status を参照
> 前提: 01-requirements.status が `approved`

## 1. 設計方針

- Laravel 12 / PostgreSQL 18 / Blade + Vite + axios + JavaScript の既存 Docker 構成に合わせる。
- パスワード認証は使わず、リクエストの `mock_user_id` から擬似ログインユーザーを解決する。
- Controller はリクエスト受け取りと JSON / View 返却に限定し、申請作成、ステータス更新、権限判定、部署制限、重複期間、在庫数、返却期限超過判定は Service に集約する。
- API は要件定義のパスに合わせて `/api/equipment-loans` 配下に定義する。Laravel 12 初期構成に `routes/api.php` がないため、本機能では `routes/web.php` に JSON 返却用ルートとして追加する。
- 返却期限超過は DB カラムとして保存せず、`status = approved` かつ `requested_to < today()` で算出する。API レスポンスには一覧行表示用の `is_overdue` と、画面上部アラート用の `overdue_summary` を含める。
- `users` テーブルは既存 Laravel 標準テーブルに `department` と `role` を追加する。パスワード認証は使わないが、既存 `email` / `password` カラムは残し、Seeder でダミー値を投入する。

## 2. Laravel

### 2.1 Controller

| クラス | 新規/変更 | メソッド | 概要 |
|--------|----------|----------|------|
| `App\Http\Controllers\EquipmentLoanPageController` | 新規 | `__invoke()` | `/equipment-loans` の Blade 画面を返す。初期ユーザー候補と備品候補は画面初期表示用に渡す |
| `App\Http\Controllers\Api\EquipmentLoanController` | 新規 | `index(ListEquipmentLoansRequest $request)` | `mock_user_id` と `search` を受け取り、ロールに応じた申請一覧、返却期限超過サマリ、ユーザー・備品候補を JSON で返す |
| `App\Http\Controllers\Api\EquipmentLoanController` | 新規 | `store(StoreEquipmentLoanRequest $request)` | 新規申請を作成し、作成結果を JSON で返す |
| `App\Http\Controllers\Api\EquipmentLoanStatusController` | 変更 | `update(UpdateEquipmentLoanStatusRequest $request, EquipmentLoanRequest $equipmentLoan)` | 申請ステータスを更新し、更新結果を JSON で返す。管理者は `approved` / `rejected` / `returned` を、申請者本人は自分の `approved` の申請に限り `return_requested` を指定できる。レスポンス組み立て時に `can_request_return`（更新後の状態と operator が本人かどうか）も算出して Presenter に渡す |

### 2.1.1 Form Request

| クラス | 新規/変更 | メソッド | 概要 |
|--------|----------|----------|------|
| `App\Http\Requests\ListEquipmentLoansRequest` | 新規 | `rules()` | `mock_user_id` 必須、存在しない ID は 404 に変換。`search` は任意文字列 |
| `App\Http\Requests\StoreEquipmentLoanRequest` | 新規 | `rules()` | `mock_user_id`, `equipment_id`, `requested_from`, `requested_to`, `reason` を検証。過去日不可、同日貸出可、終了日は開始日以降 |
| `App\Http\Requests\UpdateEquipmentLoanStatusRequest` | 変更 | `rules()` | `mock_user_id` と `status` を検証。`status` は `EquipmentLoanStatus::updatableValues()`（`approved` / `rejected` / `returned` / `return_requested`）のみ。本人・権限チェックは Service 側で行う |

### 2.2 Service

| クラス | 新規/変更 | メソッド | 概要 |
|--------|----------|----------|------|
| `App\Services\MockUserResolver` | 新規 | `resolve(int $mockUserId): User` | 擬似ログインユーザーを取得する。存在しない場合は 404 を返すための例外を投げる |
| `App\Services\EquipmentLoanQueryService` | 変更 | `listFor(User $viewer, ?string $search): array` | ロールに応じて申請一覧を取得する。`user` と `equipment` を Eager Loading し、備品名検索、`is_overdue`、`overdue_summary` を組み立てる。行ごとに `can_request_return`（`loan->user_id === viewer->id && loan->status === approved`）を判定して Presenter に渡す |
| `App\Services\EquipmentLoanApplicationService` | 新規 | `create(User $applicant, Equipment $equipment, array $attributes): EquipmentLoanRequest` | 部署制限、ユーザー重複期間、備品在庫数を検証し、`pending` で申請を登録する |
| `App\Services\EquipmentLoanApplicationService` | 新規 | `assertDepartmentAllowed(User $user, Equipment $equipment): void` | `target_department` が NULL、またはユーザー部署と一致することを検証する |
| `App\Services\EquipmentLoanApplicationService` | 変更 | `assertNoUserOverlap(User $user, CarbonInterface $from, CarbonInterface $to): void` | 同一ユーザーの `pending` / `approved` / `return_requested` 申請と期間が重複しないことを検証する |
| `App\Services\EquipmentLoanApplicationService` | 変更 | `assertStockAvailable(Equipment $equipment, CarbonInterface $from, CarbonInterface $to): void` | 同一備品の対象期間における `approved` + `return_requested` 件数が `stock_count` 未満であることを検証する |
| `App\Services\EquipmentLoanStatusService` | 変更 | `update(User $operator, EquipmentLoanRequest $loan, EquipmentLoanStatus $status): EquipmentLoanRequest` | `status = return_requested` の場合は申請者本人（`loan->user_id === operator->id`）かつ現在ステータスが `approved` であることを検証する（本人以外は 403、`approved` 以外は 422）。それ以外の指定ステータス（`approved` / `rejected` / `returned`）は従来どおり管理者のみ実行可能とし、遷移順序の制約は設けない（既存フロー維持） |
| `App\Services\EquipmentLoanPresenter` | 変更 | `toItemArray(EquipmentLoanRequest $loan, bool $canUpdateStatus = false, bool $canRequestReturn = false): array` | 一覧行用 JSON を作成する。`is_overdue`、表示用ラベルに加え、申請者本人が貸出中の自分の申請を閲覧している場合に `true` となる `can_request_return` を含める |
| `App\Services\EquipmentLoanPresenter` | 新規 | `toOverdueSummary(Collection $loans): array` | 返却期限超過件数とアラート表示用の対象概要を作成する |

### 2.3 Model / Enum

| クラス | 新規/変更 | 概要 |
|--------|----------|------|
| `App\Models\User` | 変更 | `department`, `role` を fillable / casts に追加。`loanRequests()` リレーションを追加 |
| `App\Models\Equipment` | 新規 | `equipments` テーブルの Eloquent モデル。`loanRequests()` リレーション、部署制限判定用の補助メソッドを持つ |
| `App\Models\EquipmentLoanRequest` | 変更 | `equipment_loan_requests` テーブルの Eloquent モデル。`user()` / `equipment()` リレーション、`isOverdue()` 判定を持つ。`isOverdue()` は `status` が `approved` または `return_requested` の場合を対象とする |
| `App\Enums\UserRole` | 新規 | `admin`, `staff` を表す enum |
| `App\Enums\EquipmentLoanStatus` | 変更 | `pending`, `approved`, `return_requested`, `returned`, `rejected` を表す enum。`return_requested`（返却申請中）を追加し、`updatable()` / `updatableValues()` の対象に含める |

### 2.4 Migration

| ファイル名（案） | 操作 | 概要 |
|-----------------|------|------|
| `2026_07_06_000001_add_management_fields_to_users_table.php` | 変更 | `users` に `department`（string）と `role`（string, default `staff`）を追加。`role` と `department` に index を付与 |
| `2026_07_06_000002_create_equipments_table.php` | 作成 | `id`, `name`, `stock_count`, `target_department`, timestamps を作成。`name` と `target_department` に index を付与 |
| `2026_07_06_000003_create_equipment_loan_requests_table.php` | 作成 | `user_id`, `equipment_id`, `status`, `requested_from`, `requested_to`, `reason`, timestamps を作成。FK、検索・期限切れ判定用 index を付与 |

`equipment_loan_requests` の主な index:

- `user_id`, `status`, `requested_from`, `requested_to`
- `equipment_id`, `status`, `requested_from`, `requested_to`
- `status`, `requested_to`

#### 改修: 返却申請ステータスの追加（マイグレーション不要）

`status` カラムは `string` 型（DB 側の enum/check 制約なし）のため、`return_requested` の追加は
`App\Enums\EquipmentLoanStatus` へのケース追加のみで対応し、新規マイグレーションは不要とする。

### 2.5 View / Route

| View | Route | 概要 |
|------|-------|------|
| `resources/views/equipment-loans/index.blade.php` | `GET /equipment-loans` | 備品貸出管理画面を表示する |
| - | `GET /api/equipment-loans` | 申請一覧、返却期限超過サマリ、ユーザー候補、備品候補を JSON で返す |
| - | `POST /api/equipment-loans` | 新規申請を登録する |
| - | `PATCH /api/equipment-loans/{equipmentLoan}/status` | 申請ステータスを更新する |

ルート定義案:

```php
Route::get('/equipment-loans', EquipmentLoanPageController::class)
    ->name('equipment-loans.index');

Route::prefix('api')->group(function () {
    Route::get('/equipment-loans', [EquipmentLoanController::class, 'index']);
    Route::post('/equipment-loans', [EquipmentLoanController::class, 'store']);
    Route::patch('/equipment-loans/{equipmentLoan}/status', [EquipmentLoanStatusController::class, 'update']);
});
```

### 2.6 Job（該当時）

| クラス | 概要 |
|--------|------|
| 該当なし | メール通知・定期通知は対象外。返却期限超過は画面表示時に算出する |

### 2.7 Seeder

| クラス | 新規/変更 | 概要 |
|--------|----------|------|
| `Database\Seeders\DatabaseSeeder` | 変更 | 本機能用 Seeder を呼び出す |
| `Database\Seeders\EquipmentLoanManagementSeeder` | 新規 | サンプルユーザー、備品、申請データを投入する。最初の一般社員が擬似ログイン初期ユーザーになるよう投入順を固定する |

初期データ案:

| 種別 | データ |
|------|--------|
| ユーザー | 山田 太郎（開発部, staff）、佐藤 花子（総務部, staff）、管理 太郎（総務部, admin） |
| 備品 | MacBook Pro M3（在庫 2, 開発部）、iPhone 15 Pro（在庫 1, NULL）、検証用 Windows PC（在庫 1, 総務部） |
| 申請 | 正常表示用、返却期限超過表示用、返却済表示用のサンプル申請 |

## 3. フロント（Blade / Vite / JavaScript）

### 3.1 Blade

| ファイル | 新規/変更 | 概要 |
|---------|----------|------|
| `resources/views/equipment-loans/index.blade.php` | 新規 | 画面骨格、ユーザー切り替え select、期限切れアラート領域、検索窓、申請一覧テーブル、新規申請フォームを配置する |
| `resources/views/welcome.blade.php` | 変更なし | 本機能ではルート `/` は変更しない。要件上の「TOP 画面」は備品貸出管理画面 `/equipment-loans` の上部として扱う |

Blade の主な要素:

- `#mock-user-select`: 擬似ログインユーザー選択
- `#overdue-alert`: 返却期限超過アラート領域
- `#loan-search-input`: 備品名検索
- `#loan-table-body`: 申請一覧描画先
- `#new-loan-form`: 新規申請フォーム
- `#error-message`: API エラー表示領域

### 3.2 JavaScript（Vite エントリ）

| ファイル | 新規/変更 | 概要 |
|---------|----------|------|
| `resources/js/app.js` | 変更 | `equipmentLoans.js` を import する |
| `resources/js/equipmentLoans.js` | 変更なし | 画面初期化、axios API 呼び出し、DOM 更新、ユーザー切り替え、検索、新規申請、ステータス更新を担当。ボタンクリックは `data-loan-id` / `data-status` を汎用的に読み取り同一の PATCH API を呼ぶ実装のため、返却申請ボタン追加に伴うロジック変更は不要 |
| `resources/js/equipmentLoanApi.js` | 変更なし | axios 呼び出しを薄くラップする。`updateEquipmentLoanStatus` は `status` 値を汎用的に渡す実装のため変更不要 |
| `resources/js/equipmentLoanView.js` | 変更 | 一覧行、ステータスバッジ、期限切れアラート、注意アイコンの DOM 描画を担当。`STATUS_BADGES` に `return_requested`（返却申請中）を追加し、`item.can_request_return` が `true` の行に「返却申請」ボタン（`data-testid="status-button-return_requested"`）を描画する |

<!-- resources/js/ は JavaScript のみ。TypeScript は E2E（Playwright）専用 -->

### 3.3 フロント責務

- Laravel / Service が返した `is_overdue` と `overdue_summary` をもとに、画面上部アラートと一覧行の注意アイコンを表示する。
- 管理者ユーザーの場合のみステータス更新ボタンを表示する。
- 一般社員ユーザーの場合は自分の申請のみを表示する。表示対象の絞り込み自体は API 側で担保する。
- API エラーは `message` を画面上のエラー領域に表示する。
- 新規申請成功、ステータス更新成功、ユーザー切り替え、検索時は一覧を再取得する。

## 4. シーケンス（主要フロー）

```
ユーザー → Blade → axios → Controller → Service → Model/DB
                             ↓
                        JSON レスポンス → DOM 更新
```

### 4.1 初期表示・一覧取得

```
ユーザー
  → GET /equipment-loans
  → EquipmentLoanPageController
  → equipment-loans/index.blade.php
  → resources/js/equipmentLoans.js 初期化
  → GET /api/equipment-loans?mock_user_id=<初期一般社員ID>
  → EquipmentLoanController@index
  → MockUserResolver::resolve()
  → EquipmentLoanQueryService::listFor()
  → EquipmentLoanPresenter
  → JSON(items, overdue_summary, users, equipments)
  → DOM 更新（アラート、一覧、ボタン表示）
```

### 4.2 新規申請

```
ユーザー
  → 新規申請フォーム送信
  → POST /api/equipment-loans
  → StoreEquipmentLoanRequest
  → EquipmentLoanController@store
  → MockUserResolver::resolve()
  → EquipmentLoanApplicationService::create()
      → 部署制限
      → 同一ユーザー重複期間
      → 同一備品在庫数
  → EquipmentLoanRequest 作成（status = pending）
  → JSON
  → 一覧再取得
```

### 4.3 ステータス更新

```
管理者
  → 承認 / 却下 / 返却ボタン
  → PATCH /api/equipment-loans/{equipmentLoan}/status
  → UpdateEquipmentLoanStatusRequest
  → EquipmentLoanStatusController@update
  → MockUserResolver::resolve()
  → EquipmentLoanStatusService::update()
      → role = admin を検証
      → status 更新
  → JSON
  → 一覧再取得
```

### 4.4 返却申請（改修）

```
一般社員（申請者本人）
  → 対象行の「返却申請」ボタン
  → PATCH /api/equipment-loans/{equipmentLoan}/status （status = return_requested）
  → UpdateEquipmentLoanStatusRequest
  → EquipmentLoanStatusController@update
  → MockUserResolver::resolve()
  → EquipmentLoanStatusService::update()
      → 対象が自分の申請かどうかを検証
      → 対象の現在ステータスが approved かどうかを検証
      → status = return_requested に更新
  → JSON
  → 一覧再取得
```

### 4.5 返却申請の返却確定・差し戻し（改修、既存ボタンを流用）

```
管理者
  → 返却申請中の行の「返却」ボタン（返却確定） または「承認」ボタン（差し戻し）
  → PATCH /api/equipment-loans/{equipmentLoan}/status （status = returned または approved）
  → UpdateEquipmentLoanStatusRequest
  → EquipmentLoanStatusController@update
  → MockUserResolver::resolve()
  → EquipmentLoanStatusService::update()
      → role = admin を検証（従来ロジックのまま。遷移元ステータスの制約なし）
      → status 更新
  → JSON
  → 一覧再取得
```

## 5. 影響範囲

- `users` テーブルに業務用カラムを追加するため、既存 `User` モデルと Seeder に影響がある。
- `/` の `welcome.blade.php` は変更しない。新機能は `/equipment-loans` に閉じる。
- `routes/web.php` に `/equipment-loans` と `/api/equipment-loans` 系ルートを追加する。
- `resources/js/app.js` に本機能 JS の import を追加する。既存 `bootstrap.js` の axios 設定は利用する。
- Playwright E2E はフェーズ 3 で `tests/e2e_tests/` 配下に設計する。実装フェーズまではテストコードを作成しない。
- PHPUnit は Service ロジック（部署制限、重複期間、在庫数、ステータス権限、返却期限超過判定）を対象にする想定。
- Vitest は `equipmentLoanView.js` 等の表示ロジックを切り出した場合のみ対象にする。

### 5.1 改修による影響範囲（返却申請フロー追加）

- DB マイグレーション追加は不要（`status` は string 型のため `EquipmentLoanStatus` へのケース追加のみ）。
- `EquipmentLoanStatusService::update()` の権限判定ロジックを変更する。既存の管理者向け権限判定（`approved` / `rejected` / `returned`）は変更しない。
- `EquipmentLoanApplicationService` の重複期間・在庫数チェック対象に `return_requested` を追加する。
- `EquipmentLoanRequest::isOverdue()` の対象ステータスに `return_requested` を追加する。
- `EquipmentLoanPresenter::toItemArray()` のシグネチャに `can_request_return` を追加する。呼び出し元（`EquipmentLoanQueryService`, `EquipmentLoanStatusController`）を合わせて変更する。
- `resources/js/equipmentLoanView.js` に `return_requested` のバッジと返却申請ボタン描画を追加する。`equipmentLoans.js` / `equipmentLoanApi.js` は既存の汎用実装のため変更不要。
- 既存の PHPUnit / Vitest / Playwright のテストケースのうち、変更した Service / View の挙動に関わるものは影響を受けないことを回帰確認する（`03-test-plan.md` フェーズで詳細化）。

## 6. レスポンス設計

### 6.1 `GET /api/equipment-loans`

```json
{
  "viewer": {
    "id": 1,
    "name": "山田 太郎",
    "department": "開発部",
    "role": "staff"
  },
  "items": [
    {
      "id": 10,
      "user_name": "山田 太郎",
      "equipment_name": "MacBook Pro M3",
      "status": "approved",
      "status_label": "貸出中",
      "requested_from": "2026-07-01",
      "requested_to": "2026-07-05",
      "reason": "検証作業で使用するため",
      "is_overdue": true,
      "can_update_status": false,
      "can_request_return": false
    }
  ],
  "overdue_summary": {
    "count": 1,
    "items": [
      {
        "id": 10,
        "user_name": "山田 太郎",
        "equipment_name": "MacBook Pro M3",
        "requested_to": "2026-07-05"
      }
    ]
  },
  "users": [],
  "equipments": []
}
```

### 6.2 エラーレスポンス

```json
{
  "message": "この備品は所属部署では申請できません"
}
```

Form Request のバリデーションエラーは Laravel 標準の `message` / `errors` 形式を利用する。

返却申請関連のエラー例:

```json
{
  "message": "自分の申請のみ返却申請できます"
}
```

```json
{
  "message": "現在のステータスでは返却申請できません"
}
```
