<?php

namespace Tests\Feature\Services;

use App\Enums\Gender;
use App\Models\Company;
use App\Models\Department;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\ResultFilter;
use App\Services\SurveyResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Http\Concerns\HttpTestHelpers;
use Tests\TestCase;

class SurveyResultServiceTest extends TestCase
{
    use HttpTestHelpers;
    use RefreshDatabase;

    private SurveyResultService $service;

    private Company $company;

    private Survey $survey;

    private Question $question;

    /** @var array<string, QuestionOption> label => option */
    private array $options = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SurveyResultService();

        $this->company = Company::factory()->create();
        $this->survey = Survey::factory()->published()->create(['company_id' => $this->company->id]);
        $this->question = Question::factory()->create(['survey_id' => $this->survey->id]);

        foreach (['満足' => 1, '普通' => 2, '不満' => 3] as $label => $order) {
            $this->options[$label] = QuestionOption::factory()->create([
                'question_id' => $this->question->id,
                'label' => $label,
                'sort_order' => $order,
            ]);
        }
    }

    /**
     * 指定属性のユーザーで回答 1 件（選択肢 1 つ）を作成する。
     */
    private function respond(array $userAttributes, string $optionLabel = '満足'): SurveyResponse
    {
        $user = User::factory()->create(['company_id' => $this->company->id, ...$userAttributes]);

        $response = SurveyResponse::factory()->create([
            'survey_id' => $this->survey->id,
            'user_id' => $user->id,
        ]);

        SurveyAnswer::factory()->create([
            'survey_response_id' => $response->id,
            'question_id' => $this->question->id,
            'question_option_id' => $this->options[$optionLabel]->id,
        ]);

        return $response;
    }

    /**
     * 集計結果から選択肢ラベル => 件数 の連想配列を取り出す。
     */
    private function countsFor(array $result): array
    {
        $counts = [];
        foreach ($result['questions'][0]['options'] as $option) {
            $counts[$option['label']] = $option['count'];
        }

        return $counts;
    }

    /**
     * PU-030-evt: 絞り込みなし — total_responses=3・満足=2/普通=1/不満=0
     */
    public function test_aggregate_without_filters(): void
    {
        $this->respond([], '満足');
        $this->respond([], '満足');
        $this->respond([], '普通');

        $result = $this->service->aggregate($this->survey, new ResultFilter());

        $this->assertSame(3, $result['total_responses']);
        $this->assertSame(['満足' => 2, '普通' => 1, '不満' => 0], $this->countsFor($result));
    }

    /**
     * PU-031-evt: 部署絞り込み — total_responses=1 で営業部ユーザーの回答のみ集計される
     */
    public function test_aggregate_filters_by_department(): void
    {
        $sales = Department::factory()->create(['company_id' => $this->company->id, 'name' => '営業部']);
        $general = Department::factory()->create(['company_id' => $this->company->id, 'name' => '総務部']);

        $this->respond(['department_id' => $sales->id], '満足');
        $this->respond(['department_id' => $general->id], '普通');

        $result = $this->service->aggregate($this->survey, new ResultFilter(departmentId: $sales->id));

        $this->assertSame(1, $result['total_responses']);
        $this->assertSame(['満足' => 1, '普通' => 0, '不満' => 0], $this->countsFor($result));
    }

    /**
     * PU-032-evt: 属性 NULL・絞り込み時除外 — 部署未設定の回答は部署絞り込みで集計対象外（対は PU-042）
     */
    public function test_aggregate_excludes_null_attribute_when_filtered(): void
    {
        $sales = Department::factory()->create(['company_id' => $this->company->id, 'name' => '営業部']);

        $this->respond(['department_id' => null], '満足');

        $result = $this->service->aggregate($this->survey, new ResultFilter(departmentId: $sales->id));

        $this->assertSame(0, $result['total_responses']);
    }

    /**
     * PU-042-evt: 属性 NULL・絞り込みなしで包含 — 部署未設定の回答が含まれる（対は PU-032）
     */
    public function test_aggregate_includes_null_attribute_without_filter(): void
    {
        $this->respond(['department_id' => null], '満足');

        $result = $this->service->aggregate($this->survey, new ResultFilter());

        $this->assertSame(1, $result['total_responses']);
    }

    /**
     * PU-033-evt: 削除済みユーザー — 論理削除後も回答が total と件数に含まれる（AC-14）
     */
    public function test_aggregate_includes_deleted_users_responses(): void
    {
        $response = $this->respond([], '満足');

        User::find($response->user_id)->delete();

        $result = $this->service->aggregate($this->survey, new ResultFilter());

        $this->assertSame(1, $result['total_responses']);
        $this->assertSame(['満足' => 1, '普通' => 0, '不満' => 0], $this->countsFor($result));
    }

    /**
     * PU-034-other: 年代変換(20代の境界) — 20 歳ちょうど・29 歳が含まれ、30 歳・19 歳は含まれない
     */
    public function test_aggregate_age_group_20s_boundaries(): void
    {
        $today = now()->startOfDay();

        // 誕生日当日を含む: 20 歳ちょうど = 生年月日が (today - 20 年)
        $this->respond(['birth_date' => $today->copy()->subYears(20)->toDateString()], '満足');
        $this->respond(['birth_date' => $today->copy()->subYears(30)->addDay()->toDateString()], '満足'); // 29 歳
        $this->respond(['birth_date' => $today->copy()->subYears(30)->toDateString()], '普通'); // 30 歳
        $this->respond(['birth_date' => $today->copy()->subYears(20)->addDay()->toDateString()], '普通'); // 19 歳

        $result = $this->service->aggregate($this->survey, new ResultFilter(ageGroup: '20s'));

        $this->assertSame(2, $result['total_responses']);
        $this->assertSame(['満足' => 2, '普通' => 0, '不満' => 0], $this->countsFor($result));
    }

    /**
     * PU-035-other: 年代変換(〜19歳の区分) — 19 歳のみ含まれ 20 歳は含まれない（対は PU-043）
     */
    public function test_aggregate_age_group_under20(): void
    {
        $today = now()->startOfDay();

        $this->respond(['birth_date' => $today->copy()->subYears(20)->addDay()->toDateString()], '満足'); // 19 歳
        $this->respond(['birth_date' => $today->copy()->subYears(20)->toDateString()], '普通'); // 20 歳

        $result = $this->service->aggregate($this->survey, new ResultFilter(ageGroup: 'under20'));

        $this->assertSame(1, $result['total_responses']);
        $this->assertSame(['満足' => 1, '普通' => 0, '不満' => 0], $this->countsFor($result));
    }

    /**
     * PU-043-other: 年代変換(60代〜の区分) — 60 歳のみ含まれ 59 歳は含まれない（対は PU-035）
     */
    public function test_aggregate_age_group_60plus(): void
    {
        $today = now()->startOfDay();

        $this->respond(['birth_date' => $today->copy()->subYears(60)->toDateString()], '満足'); // 60 歳
        $this->respond(['birth_date' => $today->copy()->subYears(60)->addDay()->toDateString()], '普通'); // 59 歳

        $result = $this->service->aggregate($this->survey, new ResultFilter(ageGroup: '60plus'));

        $this->assertSame(1, $result['total_responses']);
        $this->assertSame(['満足' => 1, '普通' => 0, '不満' => 0], $this->countsFor($result));
    }

    /**
     * PU-037-evt: 回答日時範囲 — updated_at 基準で date_from〜date_to 内の回答のみ集計される
     */
    public function test_aggregate_filters_by_response_date_range(): void
    {
        $this->respond([], '満足'); // 本日

        $old = $this->respond([], '普通');
        DB::table('survey_responses')->where('id', $old->id)->update([
            'updated_at' => now()->subDays(10),
        ]);

        $filter = $this->service->buildFilter([
            'date_from' => now()->subDays(3)->toDateString(),
            'date_to' => now()->toDateString(),
        ]);

        $result = $this->service->aggregate($this->survey, $filter);

        $this->assertSame(1, $result['total_responses']);
        $this->assertSame(['満足' => 1, '普通' => 0, '不満' => 0], $this->countsFor($result));
    }

    /**
     * PU-038-evt: 入社年月範囲 — 2019-01〜2021-12 で 2020-04 入社のみ集計される
     */
    public function test_aggregate_filters_by_hired_month_range(): void
    {
        $this->respond(['hired_month' => '2015-04-01'], '普通');
        $this->respond(['hired_month' => '2020-04-01'], '満足');

        $filter = $this->service->buildFilter([
            'hired_from' => '2019-01',
            'hired_to' => '2021-12',
        ]);

        $result = $this->service->aggregate($this->survey, $filter);

        $this->assertSame(1, $result['total_responses']);
        $this->assertSame(['満足' => 1, '普通' => 0, '不満' => 0], $this->countsFor($result));
    }

    /**
     * PU-039-evt: 性別絞り込み — gender=female で女性の回答のみ集計される（DB 保存値は key）
     */
    public function test_aggregate_filters_by_gender(): void
    {
        $this->respond(['gender' => Gender::Male], '普通');
        $this->respond(['gender' => Gender::Female], '満足');

        $result = $this->service->aggregate($this->survey, new ResultFilter(gender: 'female'));

        $this->assertSame(1, $result['total_responses']);
        $this->assertSame(['満足' => 1, '普通' => 0, '不満' => 0], $this->countsFor($result));
    }

    /**
     * PU-036-other: 集計性能(1000件) — 3 秒以内に完了し total_responses=1000（NFR-06 の代替検証）
     */
    public function test_aggregate_performance_with_1000_responses(): void
    {
        $users = User::factory()->count(1000)->create(['company_id' => $this->company->id]);

        $now = now();
        $responses = $users->map(fn (User $u) => [
            'survey_id' => $this->survey->id,
            'user_id' => $u->id,
            'submitted_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();
        foreach (array_chunk($responses, 200) as $chunk) {
            DB::table('survey_responses')->insert($chunk);
        }

        $optionId = $this->options['満足']->id;
        $answers = DB::table('survey_responses')
            ->where('survey_id', $this->survey->id)
            ->pluck('id')
            ->map(fn ($id) => [
                'survey_response_id' => $id,
                'question_id' => $this->question->id,
                'question_option_id' => $optionId,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();
        foreach (array_chunk($answers, 200) as $chunk) {
            DB::table('survey_answers')->insert($chunk);
        }

        $start = microtime(true);
        $result = $this->service->aggregate($this->survey, new ResultFilter());
        $elapsed = microtime(true) - $start;

        $this->assertSame(1000, $result['total_responses']);
        $this->assertSame(['満足' => 1000, '普通' => 0, '不満' => 0], $this->countsFor($result));
        $this->assertLessThan(3.0, $elapsed, "集計に {$elapsed} 秒かかりました（基準: 3 秒以内）");
    }

    /**
     * PU-148-evt: ユーザー部署変更と集計反映 — 部署変更後に総務部絞り込みで件数 1
     */
    public function test_aggregate_reflects_user_department_change_after_update(): void
    {
        $sales = Department::factory()->create(['company_id' => $this->company->id, 'name' => '営業部']);
        $general = Department::factory()->create(['company_id' => $this->company->id, 'name' => '総務部']);

        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'department_id' => $sales->id,
        ]);

        $response = SurveyResponse::factory()->create([
            'survey_id' => $this->survey->id,
            'user_id' => $user->id,
        ]);
        SurveyAnswer::factory()->create([
            'survey_response_id' => $response->id,
            'question_id' => $this->question->id,
            'question_option_id' => $this->options['満足']->id,
        ]);

        $admin = User::factory()->admin()->create([
            'company_id' => $this->company->id,
            'password' => Hash::make('pass12345'),
        ]);

        $this->actingAs($admin)
            ->withSession($this->sessionForCompany($this->company))
            ->put(route('users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => '',
                'department_id' => $general->id,
                'gender' => $user->gender->value,
                'birth_date' => '',
                'hired_month' => '',
            ])
            ->assertRedirect(route('users.index'));

        $this->assertSame($general->id, $user->fresh()->department_id);

        $result = $this->service->aggregate($this->survey, new ResultFilter(departmentId: $general->id));

        $this->assertSame(1, $result['total_responses']);
        $this->assertSame(['満足' => 1, '普通' => 0, '不満' => 0], $this->countsFor($result));
    }
}
