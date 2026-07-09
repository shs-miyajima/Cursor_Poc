<?php

namespace Tests\Feature\Http;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Http\Concerns\HttpTestHelpers;
use Tests\TestCase;

class DepartmentHttpTest extends TestCase
{
    use HttpTestHelpers;
    use RefreshDatabase;

    /**
     * PU-067-inp: 部署登録(101文字) — 「部署名は 100 文字以内で入力してください」
     */
    public function test_store_rejects_department_name_over_100_characters(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();

        $response = $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->post(route('departments.store'), ['name' => str_repeat('あ', 101)]);

        $response->assertSessionHasErrors(['name' => '部署名は 100 文字以内で入力してください']);
        $this->assertDatabaseCount('departments', 0);
    }

    /**
     * PU-068-inp: 部署登録(同一企業内重複) — 「この部署名は既に登録されています」
     */
    public function test_store_rejects_duplicate_department_name_in_same_company(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();
        $company->departments()->create(['name' => '営業部']);

        $response = $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->post(route('departments.store'), ['name' => '営業部']);

        $response->assertSessionHasErrors(['name' => 'この部署名は既に登録されています']);
        $this->assertDatabaseCount('departments', 1);
    }

    /**
     * PU-109-inp: 部署登録(100文字境界) — 登録成功し部署一覧に表示される
     */
    public function test_store_accepts_department_name_at_100_characters(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();
        $name = str_repeat('あ', 100);

        $response = $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->post(route('departments.store'), ['name' => $name]);

        $response->assertRedirect(route('departments.index'));
        $this->assertDatabaseHas('departments', ['company_id' => $company->id, 'name' => $name]);

        $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->get(route('departments.index'))
            ->assertSee($name, false);
    }

    /**
     * PU-143-inp: 部署登録(別企業同名OK) — 各社で「営業部」が登録できる
     */
    public function test_store_allows_same_department_name_in_different_companies(): void
    {
        [$companyA, $adminA] = $this->createCompanyWithAdmin(['name' => '企業A', 'code' => 'compa']);
        [$companyB, $adminB] = $this->createCompanyWithAdmin(['name' => '企業B', 'code' => 'compb']);

        $this->actingAs($adminA)
            ->withSession($this->sessionForCompany($companyA))
            ->post(route('departments.store'), ['name' => '営業部'])
            ->assertRedirect(route('departments.index'));

        $this->actingAs($adminB)
            ->withSession($this->sessionForCompany($companyB))
            ->post(route('departments.store'), ['name' => '営業部'])
            ->assertRedirect(route('departments.index'));

        $this->assertDatabaseHas('departments', ['company_id' => $companyA->id, 'name' => '営業部']);
        $this->assertDatabaseHas('departments', ['company_id' => $companyB->id, 'name' => '営業部']);
    }
}
