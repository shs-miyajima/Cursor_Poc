# 設計 — アンケート作成・回答・管理システム

> status: 02-design.status を参照
> 前提: 01-requirements.status が `approved`

## 1. 設計方針

- **レイヤ構成**: 既存規約（`.cursor/rules/laravel-conventions.mdc`）に従い、
  Controller は薄く、ビジネスロジックは `app/Services/` に置く。バリデーションは Form Request。
- **認証（企業コード + メール + パスワード）**: メールアドレスが全体一意でないため、
  Laravel 標準の `Auth::attempt()`（email のみで検索）は使わず、`AuthService` が
  企業コード → 企業解決 → 企業内ユーザー検索 → `Hash::check()` → `Auth::login()` の順で認証する。
  セッション管理・ログイン状態の仕組みは Laravel 標準（session ガード）をそのまま使う。
- **メール正規化**: `User` モデルの `email` ミューテータで常に小文字化して保存。
  ログイン・CSV 判定・重複チェックも小文字化した値で比較する（要件 §6）。
- **ロール制御**: ミドルウェア `EnsureRole`（パラメータでロール指定、複数可）をルートグループに適用。
  ロール外アクセスは 403（NFR-03）。
- **テナント分離**: `CompanyContext` サービスが「操作対象の企業」を一元解決する
  （管理者・ユーザー → 自分の `company_id`、スーパーユーザー → セッション保持の選択企業）。
  一覧・取得は必ず `where('company_id', …)` を通し、他社 ID 直指定は 404（`firstOrFail`）
  または 403 を返す（NFR-04）。
- **論理削除**: 企業・部署・ユーザー・アンケートは Eloquent `SoftDeletes`（`deleted_at`）。
  一意制約は PostgreSQL の部分ユニークインデックス（`WHERE deleted_at IS NULL`）で
  「論理削除済みを除いて一意」を実現する（要件 §6）。
- **締切の自動終了**: バッチを使わず、`Survey::effectiveStatus()` が参照時に
  「公開 かつ 締切 < 現在時刻 → 終了」と判定する（NFR-08）。DB の `status` カラムは書き換えない。
- **CSV 取込（2 段階）**: アップロード → `UserCsvImportService` が全行検証 →
  エラーありは全件表示・中断／エラー 0 件は検証済み行をセッションに保持して確認画面 →
  確定 POST でトランザクション一括反映（VAL-16〜18、AC-17/18/31/32）。
- **集計 API**: ダッシュボードは Blade で骨格を描画し、集計データは
  `GET /api/surveys/{survey}/results`（JSON）を axios で取得して Chart.js で描画する。
  絞り込みはクエリパラメータでサーバー側集計（NFR-06）。グラフ形式切替はクライアント側のみで再描画。
- **フロント**: 素の JavaScript + Tailwind（規約どおり、フレームワーク不使用）。
  Vitest 対象のロジック（Chart.js 用データ変換・設問フォームの状態操作）は
  DOM に依存しない純関数モジュール（`resources/js/modules/`）に分離する。
- **Chart.js**: npm で `chart.js`（v4）を devDependencies に追加する。
  社内ネットワークで npm 追加が失敗する場合はフェーズ 4 でエスカレーションする（既知リスク）。
- **パスワード保存（NFR-01）**: `User` モデルの `casts` `'password' => 'hashed'`（既存設定を維持）により
  Laravel 標準 bcrypt でハッシュ化して保存する。CSV 取込では検証通過時に `Hash::make()` でハッシュ化
  してからセッション保持・保存する（平文は保持しない）。
- **UI 文言の日本語化（NFR-07）**: 画面文言は Blade に日本語で直接記述する。バリデーションメッセージは
  各 Form Request の `messages()` で要件 §7 の日本語文言を指定する（`lang/` の全面日本語化は行わない）。

## 2. Laravel

### 2.1 Controller（`app/Http/Controllers/`）

| クラス | 新規/変更 | メソッド | 概要 |
|--------|----------|----------|------|
| `AuthController` | 新規 | `showLoginForm(): View` | S-01 ログイン画面表示 |
| | | `login(LoginRequest): RedirectResponse` | `AuthService::attempt()` を呼び、成功時はロール別ホームへ、失敗時は VAL-01 エラーで戻す |
| | | `logout(Request): RedirectResponse` | セッション破棄しログイン画面へ（UC-03） |
| `CompanyController` | 新規 | `index(): View` | S-02 企業一覧 + 登録フォーム |
| | | `store(StoreCompanyRequest): RedirectResponse` | 企業登録（UC-04） |
| | | `edit(Company): View` | S-02a 企業編集画面 |
| | | `update(UpdateCompanyRequest, Company): RedirectResponse` | 企業名更新（UC-05。企業コードは更新対象外） |
| | | `destroy(Company): RedirectResponse` | 論理削除（UC-06） |
| `AdminHomeController` | 新規 | `index(): View` | S-03 全体ビュー（企業数・アンケート数・回答数 + 企業一覧） |
| | | `switchCompany(SwitchCompanyRequest): RedirectResponse` | 企業ビュー切替。`CompanyContext::switchTo()` を呼びダッシュボードへ（UC-09）。企業選択解除（全体ビューに戻る）も担当 |
| `UserController` | 新規 | `index(): View` | S-04 ユーザー一覧 + 登録フォーム（`CompanyContext` の企業のみ） |
| | | `store(StoreUserRequest): RedirectResponse` | 個別登録（UC-11/12。ロールは管理者/ユーザー） |
| | | `edit(User): View` | S-04a 編集画面（他社は 404） |
| | | `update(UpdateUserRequest, User): RedirectResponse` | 更新（UC-14。企業・ロールは変更不可） |
| | | `destroy(User): RedirectResponse` | 論理削除（UC-15） |
| `UserImportController` | 新規 | `showForm(): View` | S-05 CSV アップロード画面 |
| | | `upload(ImportUserCsvRequest): View\|RedirectResponse` | 全行検証。エラーあり → S-05 にエラー一覧表示（VAL-18）。0 件 → 検証結果をセッション保存し確認画面へリダイレクト |
| | | `confirm(): View` | S-05a 確認画面（新規/更新リスト + 件数サマリ）。セッションに検証結果がなければ S-05 へ戻す |
| | | `commit(): RedirectResponse` | セッションの検証済み行を一括反映（AC-32）。完了メッセージと共に S-04 へ |
| `DepartmentController` | 新規 | `index(): View` | S-11 部署一覧 + 登録フォーム |
| | | `store(StoreDepartmentRequest): RedirectResponse` | 部署登録（UC-16） |
| | | `edit(Department): View` | S-11a 編集画面 |
| | | `update(UpdateDepartmentRequest, Department): RedirectResponse` | 部署名更新（UC-17） |
| | | `destroy(Department): RedirectResponse` | 論理削除（UC-18。所属ユーザーの `department_id` は残すが表示は「未設定」扱い） |
| `SurveyController` | 新規 | `index(): View` | S-06 アンケート一覧（管理）。`effectiveStatus` 表示 |
| | | `create(): View` | S-07 作成画面。スーパーユーザーが全体ビュー（企業未選択）の場合は企業選択 select を表示する（UC-10 の代行作成） |
| | | `store(StoreSurveyRequest): RedirectResponse` | 作成。「下書き保存」or「公開」をリクエストの `action` で分岐（UC-19）。対象企業は管理者 = 自社、SU = リクエストの `company_id`（企業ビュー選択中はその企業）で解決 |
| | | `edit(Survey): View` | S-07a 編集画面（公開後は設問部分を読み取り専用表示） |
| | | `update(UpdateSurveyRequest, Survey): RedirectResponse` | 更新（UC-20。公開後の設問変更は VAL-27） |
| | | `publish(Survey): RedirectResponse` | 下書き → 公開（VAL-26 の締切チェック含む） |
| | | `close(Survey): RedirectResponse` | 公開 → 終了（UC-21） |
| | | `destroy(Survey): RedirectResponse` | 論理削除（UC-23） |
| `DashboardController` | 新規 | `index(Request): View` | S-08 ダッシュボード骨格（アンケート選択肢・部署選択肢・初期選択 ID を Blade に渡す） |
| `SurveyResultController` | 新規 | `show(Request, Survey): JsonResponse` | 集計 API。クエリ（department_id, date_from, date_to, gender, age_group, hired_from, hired_to）を `SurveyResultService` に渡し JSON を返す（UC-24/25） |
| `MySurveyController` | 新規 | `index(): View` | S-09 回答者一覧（未回答/回答済、公開・終了のみ） |
| | | `show(Survey): View` | S-10 回答フォーム or 読み取り専用表示（UC-27〜29） |
| | | `answer(SubmitAnswerRequest, Survey): RedirectResponse` | 回答提出・修正（VAL-28〜30） |

### 2.2 Service（`app/Services/`）

| クラス | 新規/変更 | メソッド | 概要 |
|--------|----------|----------|------|
| `AuthService` | 新規 | `attempt(?string $companyCode, string $email, string $password): ?User` | 企業コード空欄 → スーパーユーザー検索（company_id NULL）。指定あり → 企業（未削除）解決 → 企業内ユーザー検索（未削除）→ `Hash::check`。企業が論理削除済みなら常に null（VAL-01） |
| | | `homeRouteFor(User $user): string` | ロール別ホームのルート名を返す（UC-01/02） |
| `CompanyContext` | 新規 | `current(): ?Company` | 操作対象企業。管理者/ユーザー → 自社。スーパーユーザー → セッションの選択企業（未選択は null = 全体ビュー） |
| | | `switchTo(?Company $company): void` | セッションに企業 ID を保存/削除 |
| | | `requireCompany(): Company` | 企業未選択のスーパーユーザーが企業必須画面（S-05/S-11 等）に来た場合は 403 |
| `UserCsvImportService` | 新規 | `validateCsv(UploadedFile $file, Company $company): CsvImportResult` | 全行パース・検証。戻り値は `rows`（行番号・値・new/update 区分・更新対象 user_id）と `errors`（行番号・項目名・理由）を持つ DTO。エラーがあっても最後まで検証する（VAL-18） |
| | | `commit(array $rows, Company $company): array{created: int, updated: int}` | トランザクションで新規 insert / 既存 update（任意列は空値でも上書き）。ロールは user 固定 |
| `SurveyService` | 新規 | `create(Company $company, User $creator, array $data, bool $publish): Survey` | 設問・選択肢込みで作成。`$publish` 時は締切チェック（VAL-26） |
| | | `update(Survey $survey, array $data): Survey` | 下書き: 設問洗い替え可。公開: タイトル・説明・締切のみ（設問変更を含む場合は ValidationException = VAL-27） |
| | | `publish(Survey $survey): void` | draft → published（締切が過去なら VAL-26） |
| | | `close(Survey $survey): void` | published → closed |
| `SurveyAnswerService` | 新規 | `submit(User $user, Survey $survey, array $answers): SurveyResponse` | `effectiveStatus` が公開中かを検証（VAL-30）。`survey_responses` を updateOrCreate、`survey_answers` は洗い替え（delete + insert）。必須設問チェックは Form Request 側 |
| `SurveyResultService` | 新規 | `aggregate(Survey $survey, ResultFilter $filter): array` | 絞り込み条件で `survey_responses` を絞り、設問ごとに選択肢別件数（選択式）/ 回答テキスト一覧（自由記述）と総回答件数を返す |
| | | `buildFilter(array $query): ResultFilter` | クエリパラメータ → フィルタ DTO（年代は生年月日範囲に変換） |
| `AgeGroupResolver`（`SurveyResultService` 内 static でも可） | 新規 | `rangeFor(string $ageGroup, CarbonInterface $now): array{from: ?Carbon, to: ?Carbon}` | 「20s」等 → 生年月日の from/to。基準日は集計実行時の現在日付 |

DTO（`app/Services/` 配下の値オブジェクト、ロジックなし）: `CsvImportResult`, `CsvImportRow`, `CsvImportError`, `ResultFilter`。

### 2.3 Model / Enum（`app/Models/`, `app/Enums/`）

| クラス | 新規/変更 | 概要 |
|--------|----------|------|
| `Company` | 新規 | `SoftDeletes`。`name`, `code`。リレーション: `users`, `departments`, `surveys` |
| `Department` | 新規 | `SoftDeletes`。`company_id`, `name`。リレーション: `company`, `users` |
| `User` | **変更** | `SoftDeletes` 追加。`fillable` に `company_id`, `role`, `department_id`, `gender`, `birth_date`, `hired_month` を追加。`casts`: `role` => `UserRole`, `gender` => `Gender`, `birth_date` => `date`。`email` ミューテータ（小文字化）。リレーション: `company`, `department`, `surveyResponses`。ヘルパ: `isSuperuser()`, `isAdmin()`, `isUser()` |
| `Survey` | 新規 | `SoftDeletes`。`company_id`, `title`, `description`, `status`（`SurveyStatus`）, `deadline_at`（datetime）, `created_by`。`effectiveStatus(): SurveyStatus`（published かつ deadline_at < now() → closed）。`isAcceptingAnswers(): bool`。リレーション: `company`, `questions`, `responses` |
| `Question` | 新規 | `survey_id`, `body`, `type`（`QuestionType`）, `is_required`, `sort_order`。リレーション: `survey`, `options`, `answers` |
| `QuestionOption` | 新規 | `question_id`, `label`, `sort_order`。リレーション: `question` |
| `SurveyResponse` | 新規 | `survey_id`, `user_id`, `submitted_at`, `updated_at`。リレーション: `survey`, `user`, `answers` |
| `SurveyAnswer` | 新規 | `survey_response_id`, `question_id`, `question_option_id`（nullable）, `text_value`（nullable）。リレーション: `response`, `question`, `option` |
| `UserRole`（Enum） | 新規 | `Superuser = 'superuser'`, `Admin = 'admin'`, `User = 'user'` |
| `SurveyStatus`（Enum） | 新規 | `Draft = 'draft'`, `Published = 'published'`, `Closed = 'closed'` + `label(): string`（日本語表示名） |
| `QuestionType`（Enum） | 新規 | `Single = 'single'`, `Multiple = 'multiple'`, `Text = 'text'` + `label(): string` |
| `Gender`（Enum） | 新規 | `Male = 'male'`, `Female = 'female'`, `Other = 'other'`, `NoAnswer = 'no_answer'`。**DB には key（英語コード）を保存**する。`label(): string` が日本語表示名（男性/女性/その他/未回答）を返し、`fromLabel(string $label): ?self` が CSV・画面の日本語文字列 → enum 変換を行う（該当なしは CSV では VAL-18 のエラー行） |

### 2.4 Form Request（`app/Http/Requests/`）

| クラス | 対象 | 主なルール（対応 VAL） |
|--------|------|------------------------|
| `LoginRequest` | S-01 | email: required/email、password: required（VAL-02）。company_code: nullable/string |
| `StoreCompanyRequest` / `UpdateCompanyRequest` | S-02/S-02a | name: required/max:100/未削除内 unique（VAL-03〜05）。Store のみ code: required/regex `[A-Za-z0-9]{1,20}`/unique（VAL-06〜08） |
| `StoreUserRequest` | S-04 | company_id（SU のみ必須）、role: in admin,user、name: required/max:100、email: required/email/同一企業内 unique（小文字化後）、password: required/min:8/max:255、department_id: nullable/exists（同一企業）、gender: nullable/enum、birth_date: nullable/date、hired_month: nullable/date_format Y-m（VAL-09〜12）。`prepareForValidation()` で email 小文字化。gender 未選択（null）は保存時に `Gender::NoAnswer`（'no_answer'）へ変換する（要件 §5.2） |
| `UpdateUserRequest` | S-04a | 同上（password: nullable=変更なし、company_id/role は受け付けない） |
| `ImportUserCsvRequest` | S-05 | file: required/mimes:csv,txt/max:2048（VAL-16）。行数・行内容の検証は `UserCsvImportService`（VAL-17/18） |
| `StoreDepartmentRequest` / `UpdateDepartmentRequest` | S-11/S-11a | name: required/max:100/同一企業内 unique（VAL-13〜15） |
| `StoreSurveyRequest` / `UpdateSurveyRequest` | S-07/S-07a | title: required/max:100（VAL-19）、description: nullable/max:1000（VAL-20）、deadline_at: nullable/date（公開時 after:now = VAL-26）、questions: required/array/min:1/max:50（VAL-21/23）、questions.*.body: required/max:500（VAL-22）、questions.*.type: enum、選択式の questions.*.options: array/min:2/max:10（VAL-24）、options.*: required/max:100（VAL-25）。Store のみ company_id: SU が全体ビューの場合 required/exists:companies（未削除）、管理者・企業ビューでは受け付けず `CompanyContext` で解決 |
| `SubmitAnswerRequest` | S-10 | 設問ごとに `is_required` に応じ required（VAL-28）、text: max:1000（VAL-29）。`withValidator()` で survey の設問構成と突合 |
| `SwitchCompanyRequest` | S-03 | company_id: nullable/exists:companies（null = 全体ビューへ戻る） |

### 2.5 Middleware / 認可

| クラス | 新規/変更 | 概要 |
|--------|----------|------|
| `EnsureRole`（`app/Http/Middleware/`） | 新規 | `handle($request, $next, ...$roles)`。未ログインは `auth` ミドルウェアが先にログイン画面へ（NFR-02）。ロール不一致は `abort(403)`（NFR-03） |

他社リソース対策（NFR-04）: ルートモデルバインディングの後、各 Controller の冒頭で
`CompanyContext::current()` と対象モデルの `company_id` を突合し、不一致は `abort(404)`。
突合ヘルパは `Controller` 基底クラスに `authorizeCompany(Model $model): void` として実装する。

### 2.6 Migration（`database/migrations/`）

| ファイル名（案） | 操作 | 概要 |
|-----------------|------|------|
| `create_companies_table` | 新規 | `id`, `name` string(100), `code` string(20), `timestamps`, `softDeletes`。部分 unique: `(name) WHERE deleted_at IS NULL`、`(code) WHERE deleted_at IS NULL`（`DB::statement` で作成） |
| `create_departments_table` | 新規 | `id`, `company_id` FK, `name` string(100), `timestamps`, `softDeletes`。部分 unique: `(company_id, name) WHERE deleted_at IS NULL` |
| `add_survey_system_columns_to_users_table` | **変更** | `users` に `company_id` FK nullable, `role` string(20) default 'user', `department_id` FK nullable, `gender` string(20) not null default 'no_answer'（`Gender` enum の **key（英語コード）を保存**。表示時に `label()` で日本語化）, `birth_date` date nullable, `hired_month` date nullable（月初日で保存）, `softDeletes` を追加。既存の `email` unique を **drop** し、部分 unique `(company_id, email) NULLS NOT DISTINCT WHERE deleted_at IS NULL` を作成（PostgreSQL 18。company_id NULL = スーパーユーザー同士も一意になる）。index: `(company_id)`, `(department_id)` |
| `create_surveys_table` | 新規 | `id`, `company_id` FK, `title` string(100), `description` text nullable, `status` string(20) default 'draft', `deadline_at` timestamp nullable, `created_by` FK(users), `timestamps`, `softDeletes`。index: `(company_id, status)`, `(created_by)` |
| `create_questions_table` | 新規 | `id`, `survey_id` FK(cascade), `body` string(500), `type` string(20), `is_required` boolean default false, `sort_order` int, `timestamps`。index: `(survey_id, sort_order)` |
| `create_question_options_table` | 新規 | `id`, `question_id` FK(cascade), `label` string(100), `sort_order` int, `timestamps`。index: `(question_id, sort_order)` |
| `create_survey_responses_table` | 新規 | `id`, `survey_id` FK, `user_id` FK, `submitted_at` timestamp, `timestamps`。unique: `(survey_id, user_id)`。index: `(survey_id, updated_at)`, `(user_id)` |
| `create_survey_answers_table` | 新規 | `id`, `survey_response_id` FK(cascade), `question_id` FK, `question_option_id` FK nullable, `text_value` text nullable, `timestamps`。index: `(survey_response_id)`, `(question_id, question_option_id)` |

#### インデックス設計一覧

PostgreSQL は FK カラムに自動でインデックスを張らないため、参照・絞り込みに使うカラムへ明示的に作成する。
部分ユニークインデックス（`WHERE deleted_at IS NULL`）は一意制約と検索の両方を兼ねる。

| テーブル | インデックス | 種別 | 用途 |
|----------|-------------|------|------|
| companies | `(name) WHERE deleted_at IS NULL` | 部分 unique | 企業名の一意制約（VAL-04）+ 名称検索 |
| companies | `(code) WHERE deleted_at IS NULL` | 部分 unique | 企業コードの一意制約（VAL-08）+ ログイン時の企業解決（毎ログインで検索） |
| departments | `(company_id, name) WHERE deleted_at IS NULL` | 部分 unique | 部署名の企業内一意（VAL-15）。先頭カラムが company_id のため自社部署一覧の検索にも有効 |
| users | `(company_id, email) NULLS NOT DISTINCT WHERE deleted_at IS NULL` | 部分 unique | メールの企業内一意（VAL-11）+ ログイン時のユーザー検索 |
| users | `(company_id)` | index | 自社ユーザー一覧・CSV 重複チェック・ダッシュボード絞り込みの結合起点 |
| users | `(department_id)` | index | 部署絞り込み（UC-25）・部署削除時の所属ユーザー参照 |
| surveys | `(company_id, status)` | index | 管理一覧（S-06）・回答者一覧（S-09）・ダッシュボード選択肢の「自社 × 状態」検索 |
| surveys | `(created_by)` | index | FK 参照整合（作成者削除時の参照）。画面での検索用途はなし |
| questions | `(survey_id, sort_order)` | index | アンケート表示時の設問取得・並び順ソート |
| question_options | `(question_id, sort_order)` | index | 設問表示時の選択肢取得・並び順ソート |
| survey_responses | `(survey_id, user_id)` | unique | 1 ユーザー 1 回答の保証 + 回答済み判定（S-09/S-10）。先頭カラムが survey_id のため回答件数集計にも有効 |
| survey_responses | `(survey_id, updated_at)` | index | 回答日時（最終更新日時）絞り込み（UC-25、NFR-06） |
| survey_responses | `(user_id)` | index | ユーザーの回答済み一覧（S-09）取得 |
| survey_answers | `(survey_response_id)` | index | 回答修正時の洗い替え（delete）・FK 参照 |
| survey_answers | `(question_id, question_option_id)` | index | 設問 × 選択肢の件数集計（GROUP BY、NFR-06） |

※ `users.gender` / `birth_date` / `hired_month` は単独インデックスを張らない。絞り込みは常に
`company_id` で母集団を絞った後の少件数評価であり、選択率の低い属性カラムの単独インデックスは
効果が薄いため（NFR-06 の 1,000 件規模では `(company_id)` index で十分と判断）。

Seeder: `SuperUserSeeder`（新規）— スーパーユーザー初期アカウント
（name: `スーパーユーザー`, email: `su@example.com`, password: `password`, role: superuser, company_id: NULL）。
`DatabaseSeeder` から呼び出しに変更（既存の Test User 作成は削除。email unique 制約変更後も動作するように）。

### 2.7 ルート定義（`routes/web.php`）

```
GET  /login                     auth.login          guest
POST /login                     AuthController@login guest
POST /logout                    AuthController@logout auth

auth + EnsureRole:superuser
  GET/POST /companies, GET /companies/{company}/edit, PUT /companies/{company}, DELETE /companies/{company}
  GET /admin/home, POST /admin/switch-company

auth + EnsureRole:superuser,admin
  GET/POST /users, GET /users/{user}/edit, PUT /users/{user}, DELETE /users/{user}
  GET/POST /users/import, GET /users/import/confirm, POST /users/import/commit
  GET/POST /departments, GET /departments/{department}/edit, PUT /departments/{department}, DELETE /departments/{department}
  GET /surveys, GET/POST /surveys/create, GET /surveys/{survey}/edit, PUT /surveys/{survey}
  POST /surveys/{survey}/publish, POST /surveys/{survey}/close, DELETE /surveys/{survey}
  GET /dashboard
  GET /api/surveys/{survey}/results

auth + EnsureRole:user
  GET /my/surveys
  GET /my/surveys/{survey}, POST /my/surveys/{survey}
```

既存の `GET /`（welcome）は変更しない（§5 IMPACT-04 参照）。

## 3. フロント（Blade / Vite / JavaScript）

### 3.1 Blade（`resources/views/`）

| ファイル | 新規/変更 | 概要 |
|---------|----------|------|
| `layouts/app.blade.php` | 新規 | 共通レイアウト。ヘッダーナビ（ロール別メニュー出し分け・企業ビュー表示・ログアウト）、フラッシュメッセージ、`@vite` 読み込み。メニュー構成 — SU（全体ビュー）: 全体ビュー/企業/ユーザー/アンケート。SU（企業ビュー）・管理者: ダッシュボード/アンケート/ユーザー/CSV 登録/部署（SU は全体ビューへ戻るリンクも表示）。ユーザー: アンケート一覧のみ |
| `auth/login.blade.php` | 新規 | S-01（企業コード・メール・パスワード） |
| `companies/index.blade.php` | 新規 | S-02（一覧 + 登録フォーム + 編集/削除導線） |
| `companies/edit.blade.php` | 新規 | S-02a |
| `admin/home.blade.php` | 新規 | S-03（サマリ 3 指標 + 企業一覧 + 企業ビュー切替ボタン） |
| `users/index.blade.php` | 新規 | S-04（一覧 + 登録フォーム。SU は企業選択、ロール選択） |
| `users/edit.blade.php` | 新規 | S-04a（企業・ロールは表示のみ） |
| `users/import.blade.php` | 新規 | S-05（アップロードフォーム + エラー一覧テーブル） |
| `users/import-confirm.blade.php` | 新規 | S-05a（新規/更新リスト + 件数サマリ + 確定/戻る） |
| `departments/index.blade.php` | 新規 | S-11 |
| `departments/edit.blade.php` | 新規 | S-11a |
| `surveys/index.blade.php` | 新規 | S-06（状態バッジ・公開/終了/編集/削除ボタン） |
| `surveys/create.blade.php` | 新規 | S-07（設問動的フォーム。`surveyForm.js` 使用） |
| `surveys/edit.blade.php` | 新規 | S-07a（下書き: create と同フォーム。公開後: 設問は読み取り専用） |
| `dashboard/index.blade.php` | 新規 | S-08（アンケート選択・絞り込みフォーム・グラフ切替・canvas 要素。`dashboard.js` 使用） |
| `my/surveys/index.blade.php` | 新規 | S-09（未回答/回答済の 2 セクション） |
| `my/surveys/show.blade.php` | 新規 | S-10（回答フォーム or 読み取り専用。ボタン表記の出し分け。終了済み・未回答の場合は「回答受付は終了しました」を表示 = UC-29） |
| `welcome.blade.php` | **変更なし** | 既存の `@if (Route::has('login'))` ブロックが `login` ルート定義により自動でログインリンクを表示するため改修不要（§5 IMPACT-04） |

### 3.2 JavaScript（`resources/js/`）

| ファイル | 新規/変更 | 概要 |
|---------|----------|------|
| `app.js` | **変更** | 既存の bootstrap（axios）読み込みに加え、`data-confirm` 属性ボタンの共通確認ダイアログ処理を追加（削除ボタン用） |
| `surveyForm.js` | 新規（Vite エントリ） | S-07/S-07a: 設問の追加/削除、選択肢の追加/削除、設問形式変更時の選択肢欄表示切替。DOM 操作のみ担当し、状態計算は `modules/surveyFormState.js` を呼ぶ |
| `modules/surveyFormState.js` | 新規（純関数） | 設問配列の追加/削除/形式変更/並び順再計算のロジック。**Vitest 対象** |
| `dashboard.js` | 新規（Vite エントリ） | S-08: axios で `/api/surveys/{id}/results` を取得し Chart.js を描画。絞り込み変更で再取得、グラフ形式切替（bar⇔pie）で再描画。自由記述はテキスト一覧を DOM に描画 |
| `modules/chartDataTransformer.js` | 新規（純関数） | 集計 JSON → Chart.js の `data` / `options` への変換（bar / pie 両対応）、0 件時の表示判定。**Vitest 対象** |

- `vite.config.js`: `input` に `resources/js/surveyForm.js`, `resources/js/dashboard.js` を追加
- `package.json`: `chart.js` を追加
- 既存 `sampleHelper.js` / `sampleHelper.test.js` は変更しない

### 3.3 集計 API レスポンス形式（`/api/surveys/{id}/results`）

```json
{
  "survey": { "id": 1, "title": "社内満足度調査 2026" },
  "total_responses": 42,
  "questions": [
    { "id": 10, "body": "…", "type": "single", "required": true,
      "options": [ { "id": 100, "label": "満足", "count": 20 }, … ] },
    { "id": 11, "body": "…", "type": "text",
      "answers": [ "…", "…" ] }
  ]
}
```

## 4. シーケンス（主要フロー）

```
【ログイン】
S-01 → POST /login → LoginRequest → AuthController@login
  → AuthService::attempt(code, email, pw) → Auth::login() → ロール別ホームへ redirect

【CSV 取込】
S-05 → POST /users/import → ImportUserCsvRequest
  → UserCsvImportService::validateCsv()
     ├ エラーあり → S-05 再表示（全エラー一覧）
     └ エラー 0 件 → セッション保存 → redirect S-05a
S-05a → POST /users/import/commit → UserCsvImportService::commit()（transaction）
  → redirect /users（新規 N 件・更新 M 件 のフラッシュ）

【回答提出・修正】
S-10 → POST /my/surveys/{id} → SubmitAnswerRequest
  → SurveyAnswerService::submit()（updateOrCreate + 明細洗い替え）
  → redirect /my/surveys（完了フラッシュ）

【ダッシュボード】
S-08 表示（Blade: 選択肢・初期選択のみ）
  → dashboard.js: axios GET /api/surveys/{id}/results?department_id=…
  → SurveyResultController@show → SurveyResultService::aggregate()
  → JSON → chartDataTransformer → Chart.js 描画
絞り込み変更 → 再取得 / グラフ切替 → 再描画のみ
```

## 5. 影響範囲

| ID | 対象 | 影響あり/なし | 内容・理由 |
|----|------|--------------|-----------|
| IMPACT-01 | `users` テーブル | **あり** | カラム 7 個追加 + 既存 `email` グローバル unique の削除（企業別一意へ変更）。既存データはローカルの seeder 由来のみで本番データなし。`DatabaseSeeder` の Test User は `SuperUserSeeder` 呼び出しに置き換える |
| IMPACT-02 | `App\Models\User` | **あり** | fillable / casts / SoftDeletes / リレーション追加。既存参照は `DatabaseSeeder`・`database/factories/UserFactory`・`config/auth.php`（プロバイダ定義、変更不要）の 3 箇所（Grep 確認済み）。Factory はデフォルト値（role=user 等）を追加する |
| IMPACT-03 | `routes/web.php` | **あり** | ルート追加。既存 `GET /`（welcome）はそのまま維持 |
| IMPACT-04 | `resources/views/welcome.blade.php` | **なし** | 既存コードに `@if (Route::has('login'))` の分岐があり、`login` ルートを定義すると自動で「Log in」リンクが表示される。ファイル変更は不要（要件 §3 の「リンク追加のみ」を満たす）。`url('/dashboard')` へのリンクも `@auth` 時のみ表示で、ダッシュボードは今回実装するため整合 |
| IMPACT-05 | `resources/js/app.js` | **あり** | 共通確認ダイアログ処理を追加。既存の `bootstrap.js`（axios 設定）は変更しない |
| IMPACT-06 | `vite.config.js` | **あり** | エントリ 2 件追加（`surveyForm.js`, `dashboard.js`）。既存エントリは維持 |
| IMPACT-07 | `package.json` | **あり** | `chart.js` を追加。社内ネットワークで npm install が失敗するリスクあり（AGENTS.md 注意事項）。失敗時はフェーズ 4 でエスカレーション |
| IMPACT-08 | 既存 E2E `tests/e2e_tests/tests/test_example.spec.ts` | **なし** | 検証内容はトップページのタイトル（`/Cursor_Poc/`）のみ。welcome のタイトルは変更しないため影響なし。ただしログインリンクが新たに表示されるようになる（タイトル検証には無関係） |
| IMPACT-09 | 既存 Vitest `resources/js/sampleHelper.test.js` | **なし** | `sampleHelper.js` は変更しない。新規 JS は別ファイルで追加 |
| IMPACT-10 | `database/seeders/DatabaseSeeder.php` | **あり** | Test User 作成を削除し `SuperUserSeeder` を呼ぶ。Test User に依存する既存機能・テストはない（Grep 確認済み） |
| IMPACT-11 | `tests/Feature/ExampleTest.php` / `tests/Unit/ExampleTest.php` | **なし** | `GET /` の 200 確認と真偽値テストのみ。welcome・既存ルートは変更しないため影響なし |
| IMPACT-12 | Docker 構成（`docker-compose.yml` / `conf/`） | **なし** | 新規コンテナ・PHP 拡張は追加しない（NFR-08）。CSV パースは PHP 標準（`SplFileObject`）、グラフはクライアント側描画のため |
| IMPACT-13 | 認証まわりの既存テーブル（`password_reset_tokens`, `sessions`） | **なし** | session ドライバはそのまま使用。パスワードリセットは要件対象外のため `password_reset_tokens` には触れない |

## 6. 設計上の注意（実装フェーズへの申し送り）

- `users.hired_month` は `date` 型に月初日（例: 2020-04-01）で保存し、表示・入力は `YYYY-MM` に変換する
- 年代絞り込みは「集計実行時点の年齢」で判定する（生年月日 → 年齢 → 10 歳刻み）
- CSV 検証結果のセッション保存はキー `user_import.pending` に平文パスワードを**含めない**
  （検証時にハッシュ化して保持する）
- 論理削除済み部署に所属するユーザーの表示は、`department` リレーションが
  `withTrashed()` なしで null になることを利用し「未設定」と表示する
- 回答日時の絞り込みは `survey_responses.updated_at`（最終更新日時）を使用（要件 §6）
- 回答者側（S-09/S-10）で下書き・論理削除済みアンケートは一覧に表示せず、URL 直指定は 404 を返す。
  終了済みへの POST は VAL-30（422）
- ダッシュボード集計（NFR-06: 回答 1,000 件で 3 秒以内）は、`survey_answers` を
  `question_id`, `question_option_id` で GROUP BY する集計クエリ + `survey_responses` の
  index `(survey_id, updated_at)` で実現する（回答明細を PHP 側でループ集計しない）
