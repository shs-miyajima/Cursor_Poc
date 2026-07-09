<?php

namespace Tests\Feature\Http;

use App\Models\Company;
use App\Models\Department;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Http\Concerns\HttpTestHelpers;
use Tests\TestCase;

class AuthorizationHttpTest extends TestCase
{
    use HttpTestHelpers;
    use RefreshDatabase;

    /**
     * PU-120-auth: GET /users/import（スーパーユーザー企業ビュー未選択）— 403
     */
    public function test_superuser_without_company_context_cannot_access_user_import(): void
    {
        $su = User::factory()->superuser()->create();

        $this->actingAs($su)
            ->get(route('users.import'))
            ->assertForbidden();
    }

    /**
     * PU-121-auth: GET /companies（管理者）— 403
     */
    public function test_admin_cannot_access_companies_index(): void
    {
        [, $admin] = $this->createCompanyWithAdmin();

        $this->actingAs($admin)
            ->get(route('companies.index'))
            ->assertForbidden();
    }

    /**
     * PU-122-auth: GET /admin/home（管理者）— 403
     */
    public function test_admin_cannot_access_admin_home(): void
    {
        [, $admin] = $this->createCompanyWithAdmin();

        $this->actingAs($admin)
            ->get(route('admin.home'))
            ->assertForbidden();
    }

    /**
     * PU-123-auth: GET /dashboard（ユーザー）— 403
     */
    public function test_user_cannot_access_dashboard(): void
    {
        [$company] = $this->createCompanyWithAdmin();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    /**
     * PU-124-auth: GET /surveys（ユーザー）— 403
     */
    public function test_user_cannot_access_surveys_index(): void
    {
        [$company] = $this->createCompanyWithAdmin();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->get(route('surveys.index'))
            ->assertForbidden();
    }

    /**
     * PU-125-auth: GET /users（ユーザー）— 403
     */
    public function test_user_cannot_access_users_index(): void
    {
        [$company] = $this->createCompanyWithAdmin();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    /**
     * PU-126-auth: GET /companies（ユーザー）— 403
     */
    public function test_user_cannot_access_companies_index(): void
    {
        [$company] = $this->createCompanyWithAdmin();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->get(route('companies.index'))
            ->assertForbidden();
    }

    /**
     * PU-127-auth: GET /departments（ユーザー）— 403
     */
    public function test_user_cannot_access_departments_index(): void
    {
        [$company] = $this->createCompanyWithAdmin();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->get(route('departments.index'))
            ->assertForbidden();
    }

    /**
     * PU-128-auth: GET /api/surveys/{id}/results（他社アンケート管理者）— 404
     */
    public function test_admin_cannot_access_other_company_survey_results(): void
    {
        [$companyA, $adminA] = $this->createCompanyWithAdmin(['code' => 'compa']);
        $companyB = Company::factory()->create(['code' => 'compb']);
        $surveyB = Survey::factory()->published()->create(['company_id' => $companyB->id]);

        $this->actingAs($adminA)
            ->withSession($this->sessionForCompany($companyA))
            ->get(route('api.surveys.results', $surveyB))
            ->assertNotFound();
    }

    /**
     * PU-129-auth: GET /users/{id}/edit（他社ユーザー管理者）— 404
     */
    public function test_admin_cannot_edit_other_company_user(): void
    {
        [$companyA, $adminA] = $this->createCompanyWithAdmin(['code' => 'compa']);
        $companyB = Company::factory()->create(['code' => 'compb']);
        $userB = User::factory()->create(['company_id' => $companyB->id]);

        $this->actingAs($adminA)
            ->withSession($this->sessionForCompany($companyA))
            ->get(route('users.edit', $userB))
            ->assertNotFound();
    }

    /**
     * PU-130-auth: GET /departments/{id}/edit（他社部署管理者）— 404
     */
    public function test_admin_cannot_edit_other_company_department(): void
    {
        [$companyA, $adminA] = $this->createCompanyWithAdmin(['code' => 'compa']);
        $companyB = Company::factory()->create(['code' => 'compb']);
        $departmentB = Department::factory()->create(['company_id' => $companyB->id]);

        $this->actingAs($adminA)
            ->withSession($this->sessionForCompany($companyA))
            ->get(route('departments.edit', $departmentB))
            ->assertNotFound();
    }

    /**
     * PU-131-auth: GET /surveys/{id}/edit（他社アンケート管理者）— 404
     */
    public function test_admin_cannot_edit_other_company_survey(): void
    {
        [$companyA, $adminA] = $this->createCompanyWithAdmin(['code' => 'compa']);
        $companyB = Company::factory()->create(['code' => 'compb']);
        $surveyB = Survey::factory()->create(['company_id' => $companyB->id]);

        $this->actingAs($adminA)
            ->withSession($this->sessionForCompany($companyA))
            ->get(route('surveys.edit', $surveyB))
            ->assertNotFound();
    }

    /**
     * PU-132-auth: GET /my/surveys/{id}（他社アンケートユーザー）— 404
     */
    public function test_user_cannot_answer_other_company_survey(): void
    {
        [$companyA] = $this->createCompanyWithAdmin(['code' => 'compa']);
        $companyB = Company::factory()->create(['code' => 'compb']);
        $userA = User::factory()->create(['company_id' => $companyA->id]);
        $surveyB = Survey::factory()->published()->create(['company_id' => $companyB->id]);

        $this->actingAs($userA)
            ->get(route('my.surveys.show', $surveyB))
            ->assertNotFound();
    }

    /**
     * PU-133-auth: GET /my/surveys（スーパーユーザー）— 403
     */
    public function test_superuser_cannot_access_my_surveys(): void
    {
        $su = User::factory()->superuser()->create();

        $this->actingAs($su)
            ->get(route('my.surveys.index'))
            ->assertForbidden();
    }

    /**
     * PU-134-auth: GET /my/surveys（管理者）— 403
     */
    public function test_admin_cannot_access_my_surveys(): void
    {
        [, $admin] = $this->createCompanyWithAdmin();

        $this->actingAs($admin)
            ->get(route('my.surveys.index'))
            ->assertForbidden();
    }

    /**
     * PU-135-auth: GET /admin/home（ユーザー）— 403
     */
    public function test_user_cannot_access_admin_home(): void
    {
        [$company] = $this->createCompanyWithAdmin();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->get(route('admin.home'))
            ->assertForbidden();
    }

    /**
     * PU-136-auth: GET /users/import（ユーザー）— 403
     */
    public function test_user_cannot_access_user_import(): void
    {
        [$company] = $this->createCompanyWithAdmin();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->get(route('users.import'))
            ->assertForbidden();
    }

    /**
     * PU-137-auth: GET /users/{id}/edit（他ユーザー）— 403
     */
    public function test_user_cannot_edit_other_user(): void
    {
        [$company] = $this->createCompanyWithAdmin();
        $user1 = User::factory()->create(['company_id' => $company->id]);
        $user2 = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user1)
            ->get(route('users.edit', $user2))
            ->assertForbidden();
    }

    /**
     * PU-138-auth: DELETE /users/{id}（他ユーザー）— 403、DB 不変
     */
    public function test_user_cannot_delete_other_user(): void
    {
        [$company] = $this->createCompanyWithAdmin();
        $user1 = User::factory()->create(['company_id' => $company->id]);
        $user2 = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user1)
            ->delete(route('users.destroy', $user2))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $user2->id, 'deleted_at' => null]);
    }

    /**
     * PU-139-auth: GET /surveys/{id}/edit（ユーザー）— 403
     */
    public function test_user_cannot_edit_survey(): void
    {
        [$company] = $this->createCompanyWithAdmin();
        $user = User::factory()->create(['company_id' => $company->id]);
        $survey = Survey::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->get(route('surveys.edit', $survey))
            ->assertForbidden();
    }

    /**
     * PU-140-auth: POST /surveys/{id}/publish（ユーザー）— 403、DB 不変
     */
    public function test_user_cannot_publish_survey(): void
    {
        [$company] = $this->createCompanyWithAdmin();
        $user = User::factory()->create(['company_id' => $company->id]);
        $survey = Survey::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->post(route('surveys.publish', $survey))
            ->assertForbidden();

        $this->assertDatabaseHas('surveys', ['id' => $survey->id, 'status' => 'draft']);
    }

    /**
     * PU-141-auth: DELETE /surveys/{id}（ユーザー）— 403、DB 不変
     */
    public function test_user_cannot_delete_survey(): void
    {
        [$company] = $this->createCompanyWithAdmin();
        $user = User::factory()->create(['company_id' => $company->id]);
        $survey = Survey::factory()->published()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->delete(route('surveys.destroy', $survey))
            ->assertForbidden();

        $this->assertDatabaseHas('surveys', ['id' => $survey->id, 'deleted_at' => null]);
    }

    /**
     * PU-142-auth: GET /departments（スーパーユーザー全体ビュー）— 403
     */
    public function test_superuser_without_company_context_cannot_access_departments(): void
    {
        $su = User::factory()->superuser()->create();

        $this->actingAs($su)
            ->get(route('departments.index'))
            ->assertForbidden();
    }

    /**
     * PU-149-auth: GET /users/import（SU 企業ビュー）— 200、CSV アップロードフォームが含まれる
     */
    public function test_superuser_with_company_context_can_access_user_import(): void
    {
        $company = Company::factory()->create();
        $su = User::factory()->superuser()->create();

        $this->actingAs($su)
            ->withSession($this->sessionForCompany($company))
            ->get(route('users.import'))
            ->assertOk()
            ->assertSee('data-testid="csv-file"', false);
    }
}
