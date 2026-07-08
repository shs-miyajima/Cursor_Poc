<?php

namespace Tests\Feature\Services;

use App\Models\Company;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AuthService();
    }

    /**
     * PU-001-auth: AuthService::attempt(管理者正常) — 該当 User モデルが返る
     */
    public function test_attempt_returns_admin_user_with_valid_credentials(): void
    {
        $company = Company::factory()->create(['code' => 'c1']);
        $admin = User::factory()->admin()->create([
            'company_id' => $company->id,
            'email' => 'admin@x.jp',
            'password' => 'pass12345',
        ]);

        $result = $this->service->attempt('c1', 'admin@x.jp', 'pass12345');

        $this->assertNotNull($result);
        $this->assertTrue($result->is($admin));
    }

    /**
     * PU-002-auth: AuthService::attempt(パスワード不一致) — null が返る
     */
    public function test_attempt_returns_null_for_wrong_password(): void
    {
        $company = Company::factory()->create(['code' => 'c1']);
        User::factory()->admin()->create([
            'company_id' => $company->id,
            'email' => 'admin@x.jp',
            'password' => 'pass12345',
        ]);

        $this->assertNull($this->service->attempt('c1', 'admin@x.jp', 'wrongpass'));
    }

    /**
     * PU-003-auth: AuthService::attempt(存在しない企業コード) — null が返る
     */
    public function test_attempt_returns_null_for_unknown_company_code(): void
    {
        $company = Company::factory()->create(['code' => 'c1']);
        User::factory()->admin()->create([
            'company_id' => $company->id,
            'email' => 'admin@x.jp',
            'password' => 'pass12345',
        ]);

        $this->assertNull($this->service->attempt('wrong99', 'admin@x.jp', 'pass12345'));
    }

    /**
     * PU-004-auth: AuthService::attempt(削除済み企業) — null が返る
     */
    public function test_attempt_returns_null_for_deleted_company(): void
    {
        $company = Company::factory()->create(['code' => 'c1']);
        User::factory()->admin()->create([
            'company_id' => $company->id,
            'email' => 'admin@x.jp',
            'password' => 'pass12345',
        ]);

        $company->delete();

        $this->assertNull($this->service->attempt('c1', 'admin@x.jp', 'pass12345'));
    }

    /**
     * PU-005-auth: AuthService::attempt(削除済みユーザー) — null が返る
     */
    public function test_attempt_returns_null_for_deleted_user(): void
    {
        $company = Company::factory()->create(['code' => 'c1']);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'email' => 'taro@x.jp',
            'password' => 'pass12345',
        ]);

        $user->delete();

        $this->assertNull($this->service->attempt('c1', 'taro@x.jp', 'pass12345'));
    }

    /**
     * PU-006-auth: AuthService::attempt(スーパーユーザー) — SU の User モデルが返る
     */
    public function test_attempt_returns_superuser_when_company_code_is_null(): void
    {
        $su = User::factory()->superuser()->create([
            'email' => 'su@x.jp',
            'password' => 'pass12345',
        ]);

        $result = $this->service->attempt(null, 'su@x.jp', 'pass12345');

        $this->assertNotNull($result);
        $this->assertTrue($result->is($su));
    }

    /**
     * PU-007-auth: AuthService::attempt(コード空欄で一般ユーザー) — null が返る（SU 検索は company_id NULL のみ対象）
     */
    public function test_attempt_returns_null_for_regular_user_without_company_code(): void
    {
        $company = Company::factory()->create(['code' => 'c1']);
        User::factory()->create([
            'company_id' => $company->id,
            'email' => 'taro@x.jp',
            'password' => 'pass12345',
        ]);

        $this->assertNull($this->service->attempt(null, 'taro@x.jp', 'pass12345'));
    }

    /**
     * PU-008-auth: AuthService::attempt(メール大文字) — 小文字正規化で照合され User が返る
     */
    public function test_attempt_normalizes_email_to_lowercase(): void
    {
        $company = Company::factory()->create(['code' => 'c1']);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'email' => 'taro@x.jp',
            'password' => 'pass12345',
        ]);

        $result = $this->service->attempt('c1', 'TARO@X.JP', 'pass12345');

        $this->assertNotNull($result);
        $this->assertTrue($result->is($user));
    }

    /**
     * PU-009-auth: AuthService::homeRouteFor(3ロール) — ロール別のルート名が返る
     */
    public function test_home_route_for_each_role(): void
    {
        $su = User::factory()->superuser()->create();
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->assertSame('admin.home', $this->service->homeRouteFor($su));
        $this->assertSame('dashboard', $this->service->homeRouteFor($admin));
        $this->assertSame('my.surveys.index', $this->service->homeRouteFor($user));
    }
}
