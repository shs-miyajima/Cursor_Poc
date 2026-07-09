<?php

namespace Tests\Feature\Http;

use App\Models\Company;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Http\Concerns\HttpTestHelpers;
use Tests\TestCase;

class DisplayFieldsHttpTest extends TestCase
{
    use HttpTestHelpers;
    use RefreshDatabase;

    /**
     * PU-144-dsp: GET 企業編集 — 企業コード入力が disabled で企業名のみ編集可能
     */
    public function test_company_edit_shows_readonly_company_code(): void
    {
        $su = User::factory()->superuser()->create();
        $company = Company::factory()->create(['code' => 'readonly01']);

        $this->actingAs($su)
            ->get(route('companies.edit', $company))
            ->assertOk()
            ->assertSee('value="'.$company->code.'"', false)
            ->assertSee('disabled', false);
    }

    /**
     * PU-145-dsp: GET ユーザー編集 — 所属企業とロールが disabled
     */
    public function test_user_edit_shows_readonly_company_and_role(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->get(route('users.edit', $user))
            ->assertOk()
            ->assertSee($company->name, false)
            ->assertSee('disabled', false);
    }

    /**
     * PU-146-dsp: GET アンケート管理一覧 — タイトル・状態・締切・回答数・作成日の列見出しと回答数 1
     */
    public function test_surveys_index_shows_list_columns_and_response_count(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();
        $survey = Survey::factory()->published()->create([
            'company_id' => $company->id,
            'title' => '表示確認アンケート',
        ]);
        $respondent = User::factory()->create(['company_id' => $company->id]);
        SurveyResponse::factory()->create([
            'survey_id' => $survey->id,
            'user_id' => $respondent->id,
        ]);

        $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->get(route('surveys.index'))
            ->assertOk()
            ->assertSee('タイトル', false)
            ->assertSee('状態', false)
            ->assertSee('締切日時', false)
            ->assertSee('回答数', false)
            ->assertSee('作成日', false)
            ->assertSee('表示確認アンケート', false)
            ->assertSee('>1<', false);
    }
}
