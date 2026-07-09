<?php

namespace Tests\Feature\Http;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Http\Concerns\HttpTestHelpers;
use Tests\TestCase;

class LoginHttpTest extends TestCase
{
    use HttpTestHelpers;
    use RefreshDatabase;

    /**
     * PU-044-inp: ログイン(パスワード未入力) — 「パスワードは必須です」
     */
    public function test_login_rejects_empty_password(): void
    {
        $response = $this->post(route('login.attempt'), [
            'company_code' => 'c1',
            'email' => 'admin@x.jp',
            'password' => '',
        ]);

        $response->assertSessionHasErrors(['password' => 'パスワードは必須です']);
        $this->assertGuest();
    }

    /**
     * PU-045-inp: ログイン(メール大文字) — 小文字正規化により認証成功し回答者一覧へ遷移
     */
    public function test_login_accepts_uppercase_email(): void
    {
        $company = Company::factory()->create(['code' => 'c1']);
        User::factory()->create([
            'company_id' => $company->id,
            'email' => 'taro@x.jp',
            'password' => Hash::make('pass12345'),
        ]);

        $response = $this->post(route('login.attempt'), [
            'company_code' => 'c1',
            'email' => 'TARO@X.JP',
            'password' => 'pass12345',
        ]);

        $response->assertRedirect(route('my.surveys.index'));
        $this->assertAuthenticated();
    }

    /**
     * PU-116-inp: ログイン(メール未入力) — 「メールアドレスは必須です」
     */
    public function test_login_rejects_empty_email(): void
    {
        $response = $this->post(route('login.attempt'), [
            'company_code' => 'c1',
            'email' => '',
            'password' => 'pass12345',
        ]);

        $response->assertSessionHasErrors(['email' => 'メールアドレスは必須です']);
        $this->assertGuest();
    }
}
