<?php

namespace Tests\Feature\Http;

use App\Enums\Gender;
use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Http\Concerns\HttpTestHelpers;
use Tests\TestCase;

class UserHttpTest extends TestCase
{
    use HttpTestHelpers;
    use RefreshDatabase;

    /**
     * PU-054-inp: ユーザー登録(メール未入力) — 「メールアドレスは必須です」
     */
    public function test_store_rejects_empty_email(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();

        $response = $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->post(route('users.store'), $this->validUserPayload($company, ['email' => '']));

        $response->assertSessionHasErrors(['email' => 'メールアドレスは必須です']);
        $this->assertDatabaseCount('users', 1);
    }

    /**
     * PU-055-inp: ユーザー登録(パスワード未入力) — 「パスワードは必須です」
     */
    public function test_store_rejects_empty_password(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();

        $response = $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->post(route('users.store'), $this->validUserPayload($company, ['password' => '']));

        $response->assertSessionHasErrors(['password' => 'パスワードは必須です']);
        $this->assertDatabaseCount('users', 1);
    }

    /**
     * PU-056-inp: ユーザー登録(メール形式不正) — 「メールアドレスの形式が正しくありません」
     */
    public function test_store_rejects_invalid_email_format(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();

        $response = $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->post(route('users.store'), $this->validUserPayload($company, ['email' => 'aaa']));

        $response->assertSessionHasErrors(['email' => 'メールアドレスの形式が正しくありません']);
        $this->assertDatabaseCount('users', 1);
    }

    /**
     * PU-057-inp: ユーザー登録(同一企業メール重複) — 「このメールアドレスは既に登録されています」
     */
    public function test_store_rejects_duplicate_email_in_same_company(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();
        User::factory()->create([
            'company_id' => $company->id,
            'email' => 'taro@x.jp',
        ]);

        $response = $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->post(route('users.store'), $this->validUserPayload($company, ['email' => 'taro@x.jp']));

        $response->assertSessionHasErrors(['email' => 'このメールアドレスは既に登録されています']);
        $this->assertDatabaseCount('users', 2);
    }

    /**
     * PU-058-inp: ユーザー登録(パスワード7文字) — 「パスワードは 8 文字以上で入力してください」
     */
    public function test_store_rejects_password_shorter_than_8_characters(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();

        $response = $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->post(route('users.store'), $this->validUserPayload($company, ['password' => '1234567']));

        $response->assertSessionHasErrors(['password' => 'パスワードは 8 文字以上で入力してください']);
        $this->assertDatabaseCount('users', 1);
    }

    /**
     * PU-059-inp: ユーザー登録(パスワード8文字境界) — 登録成功しそのパスワードでログインできる
     */
    public function test_store_accepts_password_at_8_characters_and_allows_login(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();

        $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->post(route('users.store'), $this->validUserPayload($company, [
                'email' => 'boundary8@x.jp',
                'password' => '12345678',
            ]))
            ->assertRedirect(route('users.index'));

        $this->post(route('logout'));

        $response = $this->post(route('login.attempt'), [
            'company_code' => $company->code,
            'email' => 'boundary8@x.jp',
            'password' => '12345678',
        ]);

        $response->assertRedirect(route('my.surveys.index'));
        $this->assertAuthenticated();
    }

    /**
     * PU-060-inp: ユーザー登録(氏名101文字) — 「氏名は 100 文字以内で入力してください」
     */
    public function test_store_rejects_name_over_100_characters(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();

        $response = $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->post(route('users.store'), $this->validUserPayload($company, ['name' => str_repeat('あ', 101)]));

        $response->assertSessionHasErrors(['name' => '氏名は 100 文字以内で入力してください']);
        $this->assertDatabaseCount('users', 1);
    }

    /**
     * PU-061-inp: ユーザー登録(氏名100文字境界) — 登録成功し一覧に 100 文字の氏名が表示される
     */
    public function test_store_accepts_name_at_100_characters(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();
        $name = str_repeat('あ', 100);

        $response = $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->post(route('users.store'), $this->validUserPayload($company, [
                'name' => $name,
                'email' => 'name100@x.jp',
            ]));

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', ['email' => 'name100@x.jp', 'name' => $name]);

        $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->get(route('users.index'))
            ->assertSee($name, false);
    }

    /**
     * PU-062-inp: ユーザー編集(パスワード空=変更なし) — 従来のパスワード P1 でログインできる
     */
    public function test_update_keeps_password_when_field_is_empty(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'email' => 'p1user@x.jp',
            'name' => '旧氏名',
            'password' => Hash::make('P1password'),
        ]);

        $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->put(route('users.update', $user), [
                'name' => '新氏名',
                'email' => 'p1user@x.jp',
                'password' => '',
                'department_id' => '',
                'gender' => 'no_answer',
                'birth_date' => '',
                'hired_month' => '',
            ])
            ->assertRedirect(route('users.index'));

        $this->post(route('logout'));

        $response = $this->post(route('login.attempt'), [
            'company_code' => $company->code,
            'email' => 'p1user@x.jp',
            'password' => 'P1password',
        ]);

        $response->assertRedirect(route('my.surveys.index'));
    }

    /**
     * PU-063-inp: ユーザー編集(部署空上書き) — 一覧の部署表示が「未設定」になる
     */
    public function test_update_clears_department_when_set_to_empty(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();
        $department = Department::factory()->create(['company_id' => $company->id, 'name' => '営業部']);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'department_id' => $department->id,
        ]);

        $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->put(route('users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => '',
                'department_id' => '',
                'gender' => $user->gender->value,
                'birth_date' => '',
                'hired_month' => '',
            ])
            ->assertRedirect(route('users.index'));

        $this->assertNull($user->fresh()->department_id);

        $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->get(route('users.index'))
            ->assertSee('未設定', false);
    }

    /**
     * PU-064-inp: ユーザー編集(性別未選択) — 編集画面再表示で性別が「未回答」
     */
    public function test_update_sets_gender_to_no_answer(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'gender' => Gender::Male,
        ]);

        $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->put(route('users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => '',
                'department_id' => '',
                'gender' => 'no_answer',
                'birth_date' => '',
                'hired_month' => '',
            ])
            ->assertRedirect(route('users.index'));

        $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->get(route('users.edit', $user))
            ->assertSee('未回答', false);
    }

    /**
     * PU-065-inp: ユーザー編集(生年月日空上書き) — 再表示で生年月日が空
     */
    public function test_update_clears_birth_date_when_empty(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'birth_date' => '1990-04-15',
        ]);

        $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->put(route('users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => '',
                'department_id' => '',
                'gender' => $user->gender->value,
                'birth_date' => '',
                'hired_month' => '',
            ])
            ->assertRedirect(route('users.index'));

        $this->assertNull($user->fresh()->birth_date);

        $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->get(route('users.edit', $user))
            ->assertDontSee('value="1990-04-15"', false);
    }

    /**
     * PU-066-inp: ユーザー編集(入社年月空上書き) — 再表示で入社年月が空
     */
    public function test_update_clears_hired_month_when_empty(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'hired_month' => '2015-04-01',
        ]);

        $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->put(route('users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => '',
                'department_id' => '',
                'gender' => $user->gender->value,
                'birth_date' => '',
                'hired_month' => '',
            ])
            ->assertRedirect(route('users.index'));

        $this->assertNull($user->fresh()->hired_month);

        $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->get(route('users.edit', $user))
            ->assertDontSee('value="2015-04"', false);
    }

    /**
     * PU-103-inp: ユーザー編集(メール空更新) — 「メールアドレスは必須です」、一覧のメールは変更されない
     */
    public function test_update_rejects_empty_email(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'email' => 'm1@x.jp',
        ]);

        $response = $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->put(route('users.update', $user), [
                'name' => $user->name,
                'email' => '',
                'password' => '',
                'department_id' => '',
                'gender' => $user->gender->value,
                'birth_date' => '',
                'hired_month' => '',
            ]);

        $response->assertSessionHasErrors(['email' => 'メールアドレスは必須です']);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'm1@x.jp']);
    }

    /**
     * PU-110-inp: ユーザー登録(パスワード255文字境界) — 登録成功しそのパスワードでログインできる
     */
    public function test_store_accepts_password_at_255_characters_and_allows_login(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();
        $password = str_repeat('a', 255);

        $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->post(route('users.store'), $this->validUserPayload($company, [
                'email' => 'pwd255@x.jp',
                'password' => $password,
            ]))
            ->assertRedirect(route('users.index'));

        $this->post(route('logout'));

        $response = $this->post(route('login.attempt'), [
            'company_code' => $company->code,
            'email' => 'pwd255@x.jp',
            'password' => $password,
        ]);

        $response->assertRedirect(route('my.surveys.index'));
    }

    /**
     * PU-111-inp: ユーザー登録(パスワード256文字) — 「パスワードは 255 文字以内で入力してください」
     */
    public function test_store_rejects_password_over_255_characters(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();

        $response = $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->post(route('users.store'), $this->validUserPayload($company, [
                'password' => str_repeat('a', 256),
            ]));

        $response->assertSessionHasErrors(['password' => 'パスワードは 255 文字以内で入力してください']);
        $this->assertDatabaseCount('users', 1);
    }
}
