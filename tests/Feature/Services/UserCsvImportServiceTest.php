<?php

namespace Tests\Feature\Services;

use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use App\Services\UserCsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserCsvImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = '氏名,メールアドレス,パスワード,部署,性別,生年月日,入社年月';

    private UserCsvImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UserCsvImportService();
    }

    private function makeCsvFile(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $content);

        return new UploadedFile($path, 'users.csv', 'text/csv', null, true);
    }

    /**
     * PU-012-evt: validateCsv(全行新規) — errors 0 件・rows 3 件すべて区分 new
     */
    public function test_validate_csv_all_new_rows(): void
    {
        $company = Company::factory()->create();

        $csv = implode("\n", [
            self::HEADER,
            '山田太郎,csv1@x.jp,pass12345,,男性,1990-01-01,2015-04',
            '佐藤花子,csv2@x.jp,pass12345,,女性,,',
            '鈴木一郎,csv3@x.jp,pass12345,,,,',
        ]);

        $result = $this->service->validateCsv($this->makeCsvFile($csv), $company);

        $this->assertNull($result->globalError);
        $this->assertCount(0, $result->errors);
        $this->assertCount(3, $result->rows);
        foreach ($result->rows as $row) {
            $this->assertFalse($row->isUpdate);
        }
    }

    /**
     * PU-013-evt: validateCsv(更新判定) — 既存メール一致行は区分 update で既存 user_id が設定される
     */
    public function test_validate_csv_detects_update_row(): void
    {
        $company = Company::factory()->create();
        $existing = User::factory()->create([
            'company_id' => $company->id,
            'email' => 'csv1@x.jp',
        ]);

        $csv = implode("\n", [
            self::HEADER,
            '山田太郎,csv1@x.jp,pass12345,,,,',
        ]);

        $result = $this->service->validateCsv($this->makeCsvFile($csv), $company);

        $this->assertCount(0, $result->errors);
        $this->assertCount(1, $result->rows);
        $this->assertTrue($result->rows[0]->isUpdate);
        $this->assertSame($existing->id, $result->rows[0]->userId);
    }

    /**
     * PU-014-evt: validateCsv(大文字メール更新判定) — TARO@X.JP でも既存 taro@x.jp の update 判定になる
     */
    public function test_validate_csv_matches_existing_user_case_insensitively(): void
    {
        $company = Company::factory()->create();
        User::factory()->create([
            'company_id' => $company->id,
            'email' => 'taro@x.jp',
        ]);

        $csv = implode("\n", [
            self::HEADER,
            '山田太郎,TARO@X.JP,pass12345,,,,',
        ]);

        $result = $this->service->validateCsv($this->makeCsvFile($csv), $company);

        $this->assertCount(0, $result->errors);
        $this->assertCount(1, $result->rows);
        $this->assertTrue($result->rows[0]->isUpdate);
    }

    /**
     * PU-015-evt: validateCsv(複数エラー全件) — 3 行目と 5 行目のエラーが行番号・項目名・理由付きで全件返る
     */
    public function test_validate_csv_collects_all_errors_without_stopping(): void
    {
        $company = Company::factory()->create();

        $csv = implode("\n", [
            self::HEADER,
            '正常一郎,ok1@x.jp,pass12345,,,,',
            '正常二郎,ok2@x.jp,pass12345,,,,',
            '不正三郎,invalid-email,pass12345,,,,',
            '正常四郎,ok4@x.jp,pass12345,,,,',
            '不正五郎,ok5@x.jp,pass12345,存在しない部署,,,',
        ]);

        $result = $this->service->validateCsv($this->makeCsvFile($csv), $company);

        $this->assertCount(2, $result->errors);

        $this->assertSame(3, $result->errors[0]->line);
        $this->assertSame('メールアドレス', $result->errors[0]->field);
        $this->assertSame('形式が正しくありません', $result->errors[0]->reason);

        $this->assertSame(5, $result->errors[1]->line);
        $this->assertSame('部署', $result->errors[1]->field);
        $this->assertSame('部署マスタに登録されていません', $result->errors[1]->reason);
    }

    /**
     * PU-016-evt: validateCsv(管理者メール一致) — 管理者と同じメールの行はエラーになる
     */
    public function test_validate_csv_rejects_admin_email(): void
    {
        $company = Company::factory()->create();
        User::factory()->admin()->create([
            'company_id' => $company->id,
            'email' => 'admin@x.jp',
        ]);

        $csv = implode("\n", [
            self::HEADER,
            '山田太郎,admin@x.jp,pass12345,,,,',
        ]);

        $result = $this->service->validateCsv($this->makeCsvFile($csv), $company);

        $this->assertCount(1, $result->errors);
        $this->assertSame('メールアドレス', $result->errors[0]->field);
        $this->assertSame('管理者のメールアドレスは取込できません', $result->errors[0]->reason);
        $this->assertCount(0, $result->rows);
    }

    /**
     * PU-017-evt: validateCsv(部署の大小文字照合) — 「営業g」でも「営業G」に紐付く（全角半角は区別）
     */
    public function test_validate_csv_matches_department_case_insensitively(): void
    {
        $company = Company::factory()->create();
        $department = Department::factory()->create([
            'company_id' => $company->id,
            'name' => '営業G',
        ]);

        $csv = implode("\n", [
            self::HEADER,
            '山田太郎,csv1@x.jp,pass12345,営業g,,,',
        ]);

        $result = $this->service->validateCsv($this->makeCsvFile($csv), $company);

        $this->assertCount(0, $result->errors);
        $this->assertCount(1, $result->rows);
        $this->assertSame($department->id, $result->rows[0]->departmentId);
    }

    /**
     * PU-018-evt: validateCsv(501行) — VAL-17 のエラーが返り行検証に進まない
     */
    public function test_validate_csv_rejects_more_than_500_rows(): void
    {
        $company = Company::factory()->create();

        $lines = [self::HEADER];
        for ($i = 1; $i <= 501; $i++) {
            $lines[] = "ユーザー{$i},user{$i}@x.jp,pass12345,,,,";
        }

        $result = $this->service->validateCsv($this->makeCsvFile(implode("\n", $lines)), $company);

        $this->assertSame('CSV は 500 行以内にしてください', $result->globalError);
        $this->assertCount(0, $result->rows);
        $this->assertCount(0, $result->errors);
    }

    /**
     * PU-019-evt: commit(新規+更新+空欄上書き) — created=1・updated=1、既存の氏名更新・部署 null 上書き
     */
    public function test_commit_creates_and_updates_with_blank_overwrite(): void
    {
        $company = Company::factory()->create();
        $department = Department::factory()->create(['company_id' => $company->id]);
        $existing = User::factory()->create([
            'company_id' => $company->id,
            'name' => '旧氏名',
            'email' => 'old@x.jp',
            'department_id' => $department->id,
        ]);

        $csv = implode("\n", [
            self::HEADER,
            '新規太郎,new@x.jp,pass12345,,,,',
            '更新花子,old@x.jp,pass12345,,,,',
        ]);

        $result = $this->service->validateCsv($this->makeCsvFile($csv), $company);
        $this->assertFalse($result->hasErrors());

        $summary = $this->service->commit($result->rows, $company);

        $this->assertSame(1, $summary['created']);
        $this->assertSame(1, $summary['updated']);

        $existing->refresh();
        $this->assertSame('更新花子', $existing->name);
        $this->assertNull($existing->department_id);

        $this->assertDatabaseHas('users', [
            'company_id' => $company->id,
            'email' => 'new@x.jp',
            'name' => '新規太郎',
        ]);
    }

    /**
     * PU-020-evt: commit(パスワードハッシュ) — 平文ではなくハッシュが保存され Hash::check が true
     */
    public function test_commit_stores_hashed_password(): void
    {
        $company = Company::factory()->create();

        $csv = implode("\n", [
            self::HEADER,
            '新規太郎,new@x.jp,plain12345,,,,',
        ]);

        $result = $this->service->validateCsv($this->makeCsvFile($csv), $company);
        $this->service->commit($result->rows, $company);

        $stored = DB::table('users')->where('email', 'new@x.jp')->value('password');

        $this->assertNotSame('plain12345', $stored);
        $this->assertTrue(Hash::check('plain12345', $stored));
    }

    /**
     * PU-071-inp: CSV(501行) — 「CSV は 500 行以内にしてください」
     */
    public function test_validate_csv_rejects_501_data_rows(): void
    {
        $company = Company::factory()->create();

        $lines = [self::HEADER];
        for ($i = 1; $i <= 501; $i++) {
            $lines[] = "ユーザー{$i},user{$i}@x.jp,pass12345,,,,";
        }

        $result = $this->service->validateCsv($this->makeCsvFile(implode("\n", $lines)), $company);

        $this->assertSame('CSV は 500 行以内にしてください', $result->globalError);
        $this->assertCount(0, $result->rows);
    }

    /**
     * PU-072-inp: CSV(500行境界) — validate→commit で 500 件新規作成
     */
    public function test_commit_accepts_500_data_rows(): void
    {
        $company = Company::factory()->create();

        $lines = [self::HEADER];
        for ($i = 1; $i <= 500; $i++) {
            $lines[] = "ユーザー{$i},bulk{$i}@x.jp,pass12345,,,,";
        }

        $result = $this->service->validateCsv($this->makeCsvFile(implode("\n", $lines)), $company);
        $this->assertFalse($result->hasErrors());

        $summary = $this->service->commit($result->rows, $company);

        $this->assertSame(500, $summary['created']);
        $this->assertSame(0, $summary['updated']);
        $this->assertSame(500, User::where('company_id', $company->id)->where('role', 'user')->count());
    }

    /**
     * PU-073-inp: CSV(氏名欠落行) — 「2 行目: 氏名 — 必須です」
     */
    public function test_validate_csv_rejects_missing_name_on_line_2(): void
    {
        $company = Company::factory()->create();

        $csv = implode("\n", [
            self::HEADER,
            '正常一郎,ok1@x.jp,pass12345,,,,',
            ',bad2@x.jp,pass12345,,,,',
            '正常三郎,ok3@x.jp,pass12345,,,,',
        ]);

        $result = $this->service->validateCsv($this->makeCsvFile($csv), $company);

        $this->assertTrue($result->hasErrors());
        $this->assertSame('2 行目: 氏名 — 必須です', $result->errors[0]->message());
        $this->assertCount(2, $result->rows);
    }

    /**
     * PU-074-inp: CSV(メール形式不正行) — 「2 行目: メールアドレス — 形式が正しくありません」
     */
    public function test_validate_csv_rejects_invalid_email_on_line_2(): void
    {
        $company = Company::factory()->create();

        $csv = implode("\n", [
            self::HEADER,
            '正常一郎,ok1@x.jp,pass12345,,,,',
            '不正二郎,aaa,pass12345,,,,',
        ]);

        $result = $this->service->validateCsv($this->makeCsvFile($csv), $company);

        $this->assertSame('2 行目: メールアドレス — 形式が正しくありません', $result->errors[0]->message());
    }

    /**
     * PU-075-inp: CSV(ファイル内メール重複) — 「3 行目: メールアドレス — ファイル内で重複しています」
     */
    public function test_validate_csv_rejects_duplicate_email_in_file(): void
    {
        $company = Company::factory()->create();

        $csv = implode("\n", [
            self::HEADER,
            '一郎,dup@x.jp,pass12345,,,,',
            '二郎,ok2@x.jp,pass12345,,,,',
            '三郎,dup@x.jp,pass12345,,,,',
        ]);

        $result = $this->service->validateCsv($this->makeCsvFile($csv), $company);

        $this->assertSame('3 行目: メールアドレス — ファイル内で重複しています', $result->errors[0]->message());
    }

    /**
     * PU-076-inp: CSV(既存管理者と同一メール) — 「1 行目: メールアドレス — 管理者のメールアドレスは取込できません」
     */
    public function test_validate_csv_rejects_admin_email_on_line_1(): void
    {
        $company = Company::factory()->create();
        User::factory()->admin()->create([
            'company_id' => $company->id,
            'email' => 'admin@x.jp',
        ]);

        $csv = implode("\n", [
            self::HEADER,
            '山田太郎,admin@x.jp,pass12345,,,,',
        ]);

        $result = $this->service->validateCsv($this->makeCsvFile($csv), $company);

        $this->assertSame('1 行目: メールアドレス — 管理者のメールアドレスは取込できません', $result->errors[0]->message());
    }

    /**
     * PU-077-inp: CSV(パスワード7文字行) — 「2 行目: パスワード — 8 文字以上で入力してください」
     */
    public function test_validate_csv_rejects_short_password_on_line_2(): void
    {
        $company = Company::factory()->create();

        $csv = implode("\n", [
            self::HEADER,
            '正常一郎,ok1@x.jp,pass12345,,,,',
            '不正二郎,bad2@x.jp,1234567,,,,',
        ]);

        $result = $this->service->validateCsv($this->makeCsvFile($csv), $company);

        $this->assertSame('2 行目: パスワード — 8 文字以上で入力してください', $result->errors[0]->message());
    }

    /**
     * PU-078-inp: CSV(性別不正値) — 「1 行目: 性別 — 男性/女性/その他/未回答 のいずれかで入力してください」
     */
    public function test_validate_csv_rejects_invalid_gender_label(): void
    {
        $company = Company::factory()->create();

        $csv = implode("\n", [
            self::HEADER,
            '山田太郎,csv1@x.jp,pass12345,,男,,',
        ]);

        $result = $this->service->validateCsv($this->makeCsvFile($csv), $company);

        $this->assertSame('1 行目: 性別 — 男性/女性/その他/未回答 のいずれかで入力してください', $result->errors[0]->message());
    }

    /**
     * PU-079-inp: CSV(生年月日形式不正) — 「1 行目: 生年月日 — YYYY-MM-DD 形式で入力してください」
     */
    public function test_validate_csv_rejects_invalid_birth_date_format(): void
    {
        $company = Company::factory()->create();

        $csv = implode("\n", [
            self::HEADER,
            '山田太郎,csv1@x.jp,pass12345,,,1990/04/15,',
        ]);

        $result = $this->service->validateCsv($this->makeCsvFile($csv), $company);

        $this->assertSame('1 行目: 生年月日 — YYYY-MM-DD 形式で入力してください', $result->errors[0]->message());
    }

    /**
     * PU-080-inp: CSV(入社年月形式不正) — 「1 行目: 入社年月 — YYYY-MM 形式で入力してください」
     */
    public function test_validate_csv_rejects_invalid_hired_month_format(): void
    {
        $company = Company::factory()->create();

        $csv = implode("\n", [
            self::HEADER,
            '山田太郎,csv1@x.jp,pass12345,,,,2015-04-01',
        ]);

        $result = $this->service->validateCsv($this->makeCsvFile($csv), $company);

        $this->assertSame('1 行目: 入社年月 — YYYY-MM 形式で入力してください', $result->errors[0]->message());
    }

    /**
     * PU-081-inp: CSV(部署マスタ未登録) — 「1 行目: 部署 — 部署マスタに登録されていません」
     */
    public function test_validate_csv_rejects_unregistered_department(): void
    {
        $company = Company::factory()->create();

        $csv = implode("\n", [
            self::HEADER,
            '山田太郎,csv1@x.jp,pass12345,企画部,,,',
        ]);

        $result = $this->service->validateCsv($this->makeCsvFile($csv), $company);

        $this->assertSame('1 行目: 部署 — 部署マスタに登録されていません', $result->errors[0]->message());
    }

    /**
     * PU-112-inp: CSV(パスワード255文字境界) — 取込成功しそのパスワードでログインできる
     */
    public function test_commit_accepts_password_at_255_characters(): void
    {
        $company = Company::factory()->create(['code' => 'pwd255co']);
        $password = str_repeat('a', 255);

        $csv = implode("\n", [
            self::HEADER,
            "山田太郎,pwd255user@x.jp,{$password},,,,",
        ]);

        $result = $this->service->validateCsv($this->makeCsvFile($csv), $company);
        $this->service->commit($result->rows, $company);

        $this->assertTrue(Hash::check($password, User::where('email', 'pwd255user@x.jp')->value('password')));
    }

    /**
     * PU-113-inp: CSV(パスワード256文字行) — 「1 行目: パスワード — 8〜255 文字で入力してください」
     */
    public function test_validate_csv_rejects_password_over_255_characters(): void
    {
        $company = Company::factory()->create();
        $password = str_repeat('a', 256);

        $csv = implode("\n", [
            self::HEADER,
            "山田太郎,csv1@x.jp,{$password},,,,",
        ]);

        $result = $this->service->validateCsv($this->makeCsvFile($csv), $company);

        $this->assertSame('1 行目: パスワード — 8〜255 文字で入力してください', $result->errors[0]->message());
    }

    /**
     * PU-114-inp: CSV(氏名100文字境界) — 取込成功し 100 文字の氏名が保存される
     */
    public function test_commit_accepts_name_at_100_characters(): void
    {
        $company = Company::factory()->create();
        $name = str_repeat('あ', 100);

        $csv = implode("\n", [
            self::HEADER,
            "{$name},name100@x.jp,pass12345,,,,",
        ]);

        $result = $this->service->validateCsv($this->makeCsvFile($csv), $company);
        $this->service->commit($result->rows, $company);

        $this->assertDatabaseHas('users', [
            'company_id' => $company->id,
            'email' => 'name100@x.jp',
            'name' => $name,
        ]);
    }

    /**
     * PU-115-inp: CSV(氏名101文字行) — 「1 行目: 氏名 — 100 文字以内で入力してください」
     */
    public function test_validate_csv_rejects_name_over_100_characters(): void
    {
        $company = Company::factory()->create();
        $name = str_repeat('あ', 101);

        $csv = implode("\n", [
            self::HEADER,
            "{$name},csv1@x.jp,pass12345,,,,",
        ]);

        $result = $this->service->validateCsv($this->makeCsvFile($csv), $company);

        $this->assertSame('1 行目: 氏名 — 100 文字以内で入力してください', $result->errors[0]->message());
    }
}
