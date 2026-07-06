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
| `App\Http\Controllers\Api\EquipmentLoanStatusController` | 新規 | `update(UpdateEquipmentLoanStatusRequest $request, EquipmentLoanRequest $equipmentLoan)` | 管理者権限で申請ステータスを更新し、更新結果を JSON で返す |

### 2.1.1 Form Request

| クラス | 新規/変更 | メソッド | 概要 |
|--------|----------|----------|------|
| `App\Http\Requests\ListEquipmentLoansRequest` | 新規 | `rules()` | `mock_user_id` 必須、存在しない ID は 404 に変換。`search` は任意文字列 |
| `App\Http\Requests\StoreEquipmentLoanRequest` | 新規 | `rules()` | `mock_user_id`, `equipment_id`, `requested_from`, `requested_to`, `reason` を検証。過去日不可、同日貸出可、終了日は開始日以降 |
| `App\Http\Requests\UpdateEquipmentLoanStatusRequest` | 新規 | `rules()` | `mock_user_id` と `status` を検証。`status` は `approved` / `rejected` / `returned` のみ |

### 2.2 Service

| クラス | 新規/変更 | メソッド | 概要 |
|--------|----------|----------|------|
| `App\Services\MockUserResolver` | 新規 | `resolve(int $mockUserId): User` | 擬似ログインユーザーを取得する。存在しない場合は 404 を返すための例外を投げる |
| `App\Services\EquipmentLoanQueryService` | 新規 | `listFor(User $viewer, ?string $search): array` | ロールに応じて申請一覧を取得する。`user` と `equipment` を Eager Loading し、備品名検索、`is_overdue`、`overdue_summary` を組み立てる |
| `App\Services\EquipmentLoanApplicationService` | 新規 | `create(User $applicant, Equipment $equipment, array $attributes): EquipmentLoanRequest` | 部署制限、ユーザー重複期間、備品在庫数を検証し、`pending` で申請を登録する |
| `App\Services\EquipmentLoanApplicationService` | 新規 | `assertDepartmentAllowed(User $user, Equipment $equipment): void` | `target_department` が NULL、またはユーザー部署と一致することを検証する |
| `App\Services\EquipmentLoanApplicationService` | 新規 | `assertNoUserOverlap(User $user, CarbonInterface $from, CarbonInterface $to): void` | 同一ユーザーの `pending` / `approved` 申請と期間が重複しないことを検証する |
| `App\Services\EquipmentLoanApplicationService` | 新規 | `assertStockAvailable(Equipment $equipment, CarbonInterface $from, CarbonInterface $to): void` | 同一備品の対象期間における `approved` 件数が `stock_count` 未満であることを検証する |
| `App\Services\EquipmentLoanStatusService` | 新規 | `update(User $operator, EquipmentLoanRequest $loan, EquipmentLoanStatus $status): EquipmentLoanRequest` | 管理者のみステータスを `approved` / `rejected` / `returned` に更新する。ステータス遷移順序の制約は設けない |
| `App\Services\EquipmentLoanPresenter` | 新規 | `toItemArray(EquipmentLoanRequest $loan): array` | 一覧行用 JSON を作成する。`is_overdue` と表示用ラベルを含める |
| `App\Services\EquipmentLoanPresenter` | 新規 | `toOverdueSummary(Collection $loans): array` | 返却期限超過件数とアラート表示用の対象概要を作成する |

### 2.3 Model / Enum

| クラス | 新規/変更 | 概要 |
|--------|----------|------|
| `App\Models\User` | 変更 | `department`, `role` を fillable / casts に追加。`loanRequests()` リレーションを追加 |
| `App\Models\Equipment` | 新規 | `equipments` テーブルの Eloquent モデル。`loanRequests()` リレーション、部署制限判定用の補助メソッドを持つ |
| `App\Models\EquipmentLoanRequest` | 新規 | `equipment_loan_requests` テーブルの Eloquent モデル。`user()` / `equipment()` リレーション、`isOverdue()` 判定を持つ |
| `App\Enums\UserRole` | 新規 | `admin`, `staff` を表す enum |
| `App\Enums\EquipmentLoanStatus` | 新規 | `pending`, `approved`, `returned`, `rejected` を表す enum。ステータス更新で許可する値を返すメソッドを持つ |

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
| `resources/js/equipmentLoans.js` | 新規 | 画面初期化、axios API 呼び出し、DOM 更新、ユーザー切り替え、検索、新規申請、ステータス更新を担当 |
| `resources/js/equipmentLoanApi.js` | 新規 | axios 呼び出しを薄くラップする |
| `resources/js/equipmentLoanView.js` | 新規 | 一覧行、ステータスバッジ、期限切れアラート、注意アイコンの DOM 描画を担当 |

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

## 5. 影響範囲

- `users` テーブルに業務用カラムを追加するため、既存 `User` モデルと Seeder に影響がある。
- `/` の `welcome.blade.php` は変更しない。新機能は `/equipment-loans` に閉じる。
- `routes/web.php` に `/equipment-loans` と `/api/equipment-loans` 系ルートを追加する。
- `resources/js/app.js` に本機能 JS の import を追加する。既存 `bootstrap.js` の axios 設定は利用する。
- Playwright E2E はフェーズ 3 で `tests/e2e_tests/` 配下に設計する。実装フェーズまではテストコードを作成しない。
- PHPUnit は Service ロジック（部署制限、重複期間、在庫数、ステータス権限、返却期限超過判定）を対象にする想定。
- Vitest は `equipmentLoanView.js` 等の表示ロジックを切り出した場合のみ対象にする。

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
      "can_update_status": false
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
