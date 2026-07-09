<?php

namespace Tests\Feature\Http;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Http\Concerns\HttpTestHelpers;
use Tests\TestCase;

class CompanyHttpTest extends TestCase
{
    use HttpTestHelpers;
    use RefreshDatabase;

    private User $superuser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superuser = User::factory()->superuser()->create();
    }

    /**
     * PU-046-inp: 企業登録(企業名重複) — 「この企業名は既に登録されています」
     */
    public function test_store_rejects_duplicate_company_name(): void
    {
        $existing = Company::factory()->create(['name' => '重複企業', 'code' => 'dup01']);

        $response = $this->actingAs($this->superuser)->post(route('companies.store'), [
            'name' => '重複企業',
            'code' => 'dup02',
        ]);

        $response->assertSessionHasErrors(['name' => 'この企業名は既に登録されています']);
        $this->assertDatabaseCount('companies', 1);
        $this->assertDatabaseHas('companies', ['id' => $existing->id]);
    }

    /**
     * PU-047-inp: 企業登録(企業名101文字) — 「企業名は 100 文字以内で入力してください」
     */
    public function test_store_rejects_company_name_over_100_characters(): void
    {
        $response = $this->actingAs($this->superuser)->post(route('companies.store'), [
            'name' => str_repeat('あ', 101),
            'code' => 'name101',
        ]);

        $response->assertSessionHasErrors(['name' => '企業名は 100 文字以内で入力してください']);
        $this->assertDatabaseCount('companies', 0);
    }

    /**
     * PU-048-inp: 企業登録(企業名100文字境界) — 登録成功し一覧に表示される
     */
    public function test_store_accepts_company_name_at_100_characters(): void
    {
        $name = str_repeat('あ', 100);

        $response = $this->actingAs($this->superuser)->post(route('companies.store'), [
            'name' => $name,
            'code' => 'name100ok',
        ]);

        $response->assertRedirect(route('companies.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('companies', ['name' => $name, 'code' => 'name100ok']);

        $this->actingAs($this->superuser)
            ->get(route('companies.index'))
            ->assertSee($name, false);
    }

    /**
     * PU-049-inp: 企業登録(コード未入力) — 「企業コードは必須です」
     */
    public function test_store_rejects_empty_company_code(): void
    {
        $response = $this->actingAs($this->superuser)->post(route('companies.store'), [
            'name' => '新規企業',
            'code' => '',
        ]);

        $response->assertSessionHasErrors(['code' => '企業コードは必須です']);
        $this->assertDatabaseCount('companies', 0);
    }

    /**
     * PU-050-inp: 企業登録(コード記号混入) — 「企業コードは半角英数字 20 文字以内で入力してください」
     */
    public function test_store_rejects_company_code_with_symbols(): void
    {
        $response = $this->actingAs($this->superuser)->post(route('companies.store'), [
            'name' => '新規企業',
            'code' => 'abc-01!',
        ]);

        $response->assertSessionHasErrors(['code' => '企業コードは半角英数字 20 文字以内で入力してください']);
        $this->assertDatabaseCount('companies', 0);
    }

    /**
     * PU-051-inp: 企業登録(コード21文字) — 「企業コードは半角英数字 20 文字以内で入力してください」
     */
    public function test_store_rejects_company_code_over_20_characters(): void
    {
        $response = $this->actingAs($this->superuser)->post(route('companies.store'), [
            'name' => '新規企業',
            'code' => str_repeat('a', 21),
        ]);

        $response->assertSessionHasErrors(['code' => '企業コードは半角英数字 20 文字以内で入力してください']);
        $this->assertDatabaseCount('companies', 0);
    }

    /**
     * PU-052-inp: 企業登録(コード20文字境界) — 登録成功し一覧に表示される
     */
    public function test_store_accepts_company_code_at_20_characters(): void
    {
        $code = str_repeat('a', 20);

        $response = $this->actingAs($this->superuser)->post(route('companies.store'), [
            'name' => 'コード20文字企業',
            'code' => $code,
        ]);

        $response->assertRedirect(route('companies.index'));
        $this->assertDatabaseHas('companies', ['code' => $code]);

        $this->actingAs($this->superuser)
            ->get(route('companies.index'))
            ->assertSee($code, false);
    }

    /**
     * PU-053-inp: 企業登録(コード重複) — 「この企業コードは既に登録されています」
     */
    public function test_store_rejects_duplicate_company_code(): void
    {
        Company::factory()->create(['name' => '既存企業', 'code' => 'samecode']);

        $response = $this->actingAs($this->superuser)->post(route('companies.store'), [
            'name' => '別名企業',
            'code' => 'samecode',
        ]);

        $response->assertSessionHasErrors(['code' => 'この企業コードは既に登録されています']);
        $this->assertDatabaseCount('companies', 1);
    }
}
