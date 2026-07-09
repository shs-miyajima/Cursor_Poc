<?php

namespace Tests\Feature\Http;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Survey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Http\Concerns\HttpTestHelpers;
use Tests\TestCase;

class SurveyHttpTest extends TestCase
{
    use HttpTestHelpers;
    use RefreshDatabase;

    private function postSurveyAsAdmin(array $payload)
    {
        [$company, $admin] = $this->createCompanyWithAdmin();

        return [
            $company,
            $admin,
            $this->actingAs($admin)
                ->withSession($this->sessionForCompany($company))
                ->post(route('surveys.store'), $payload),
        ];
    }

    /**
     * PU-082-inp: アンケート(タイトル101文字) — 「タイトルは 100 文字以内で入力してください」
     */
    public function test_store_rejects_title_over_100_characters(): void
    {
        [, , $response] = $this->postSurveyAsAdmin($this->validSurveyPayload([
            'title' => str_repeat('あ', 101),
        ]));

        $response->assertSessionHasErrors(['title' => 'タイトルは 100 文字以内で入力してください']);
        $this->assertDatabaseCount('surveys', 0);
    }

    /**
     * PU-083-inp: アンケート(タイトル100文字境界) — 作成成功し管理一覧に表示される
     */
    public function test_store_accepts_title_at_100_characters(): void
    {
        $title = str_repeat('あ', 100);
        [$company, $admin, $response] = $this->postSurveyAsAdmin($this->validSurveyPayload(['title' => $title]));

        $response->assertRedirect(route('surveys.index'));
        $this->assertDatabaseHas('surveys', ['company_id' => $company->id, 'title' => $title]);

        $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->get(route('surveys.index'))
            ->assertSee($title, false);
    }

    /**
     * PU-084-inp: アンケート(説明1001文字) — 「説明は 1000 文字以内で入力してください」
     */
    public function test_store_rejects_description_over_1000_characters(): void
    {
        [, , $response] = $this->postSurveyAsAdmin($this->validSurveyPayload([
            'description' => str_repeat('あ', 1001),
        ]));

        $response->assertSessionHasErrors(['description' => '説明は 1000 文字以内で入力してください']);
        $this->assertDatabaseCount('surveys', 0);
    }

    /**
     * PU-085-inp: アンケート(設問0問) — 「設問を 1 問以上追加してください」
     */
    public function test_store_rejects_empty_questions(): void
    {
        [, , $response] = $this->postSurveyAsAdmin($this->validSurveyPayload([
            'questions' => [],
        ]));

        $response->assertSessionHasErrors(['questions']);
        $this->assertDatabaseCount('surveys', 0);
    }

    /**
     * PU-086-inp: アンケート(設問文未入力) — 「設問文は必須です」
     */
    public function test_store_rejects_empty_question_body(): void
    {
        [, , $response] = $this->postSurveyAsAdmin($this->validSurveyPayload([
            'questions' => [
                [
                    'body' => '',
                    'type' => 'single',
                    'is_required' => '1',
                    'options' => ['A', 'B'],
                ],
            ],
        ]));

        $response->assertSessionHasErrors(['questions.0.body' => '設問文は必須です']);
        $this->assertDatabaseCount('surveys', 0);
    }

    /**
     * PU-087-inp: アンケート(設問文501文字) — 「設問文は 500 文字以内で入力してください」
     */
    public function test_store_rejects_question_body_over_500_characters(): void
    {
        [, , $response] = $this->postSurveyAsAdmin($this->validSurveyPayload([
            'questions' => [
                [
                    'body' => str_repeat('あ', 501),
                    'type' => 'single',
                    'is_required' => '1',
                    'options' => ['A', 'B'],
                ],
            ],
        ]));

        $response->assertSessionHasErrors(['questions.0.body' => '設問文は 500 文字以内で入力してください']);
        $this->assertDatabaseCount('surveys', 0);
    }

    /**
     * PU-088-inp: アンケート(設問51問) — 「設問は 50 問以内にしてください」
     */
    public function test_store_rejects_more_than_50_questions(): void
    {
        [, , $response] = $this->postSurveyAsAdmin($this->validSurveyPayload([
            'questions' => $this->makeQuestions(51),
        ]));

        $response->assertSessionHasErrors(['questions' => '設問は 50 問以内にしてください']);
        $this->assertDatabaseCount('surveys', 0);
    }

    /**
     * PU-089-inp: アンケート(設問50問境界) — 作成成功し 50 問が保存される
     */
    public function test_store_accepts_50_questions(): void
    {
        [$company, , $response] = $this->postSurveyAsAdmin($this->validSurveyPayload([
            'questions' => $this->makeQuestions(50),
        ]));

        $response->assertRedirect(route('surveys.index'));
        $survey = Survey::where('company_id', $company->id)->first();
        $this->assertNotNull($survey);
        $this->assertSame(50, $survey->questions()->count());
    }

    /**
     * PU-090-inp: アンケート(選択肢1個) — 「選択肢は 2 個以上 10 個以内で入力してください」
     */
    public function test_store_rejects_single_option(): void
    {
        [, , $response] = $this->postSurveyAsAdmin($this->validSurveyPayload([
            'questions' => $this->makeQuestionsWithOptions(1),
        ]));

        $response->assertSessionHasErrors(['questions.0.options']);
        $this->assertDatabaseCount('surveys', 0);
    }

    /**
     * PU-091-inp: アンケート(選択肢11個) — 「選択肢は 2 個以上 10 個以内で入力してください」
     */
    public function test_store_rejects_more_than_10_options(): void
    {
        [, , $response] = $this->postSurveyAsAdmin($this->validSurveyPayload([
            'questions' => $this->makeQuestionsWithOptions(11),
        ]));

        $response->assertSessionHasErrors(['questions.0.options']);
        $this->assertDatabaseCount('surveys', 0);
    }

    /**
     * PU-092-inp: アンケート(選択肢2個境界) — 作成成功する
     */
    public function test_store_accepts_two_options(): void
    {
        [$company, , $response] = $this->postSurveyAsAdmin($this->validSurveyPayload([
            'questions' => $this->makeQuestionsWithOptions(2),
        ]));

        $response->assertRedirect(route('surveys.index'));
        $survey = Survey::where('company_id', $company->id)->first();
        $this->assertSame(2, $survey->questions()->first()->options()->count());
    }

    /**
     * PU-093-inp: アンケート(選択肢10個境界) — 作成成功し 10 個の選択肢が保存される
     */
    public function test_store_accepts_ten_options(): void
    {
        [$company, , $response] = $this->postSurveyAsAdmin($this->validSurveyPayload([
            'questions' => $this->makeQuestionsWithOptions(10),
        ]));

        $response->assertRedirect(route('surveys.index'));
        $survey = Survey::where('company_id', $company->id)->first();
        $this->assertSame(10, $survey->questions()->first()->options()->count());
    }

    /**
     * PU-094-inp: アンケート(選択肢ラベル空) — 「選択肢は必須です」
     */
    public function test_store_rejects_empty_option_label(): void
    {
        [, , $response] = $this->postSurveyAsAdmin($this->validSurveyPayload([
            'questions' => [
                [
                    'body' => '設問1',
                    'type' => 'single',
                    'is_required' => '1',
                    'options' => ['A', ''],
                ],
            ],
        ]));

        $response->assertSessionHasErrors(['questions.0.options.1' => '選択肢は必須です']);
        $this->assertDatabaseCount('surveys', 0);
    }

    /**
     * PU-095-inp: アンケート(選択肢101文字) — 「選択肢は 100 文字以内で入力してください」
     */
    public function test_store_rejects_option_label_over_100_characters(): void
    {
        [, , $response] = $this->postSurveyAsAdmin($this->validSurveyPayload([
            'questions' => [
                [
                    'body' => '設問1',
                    'type' => 'single',
                    'is_required' => '1',
                    'options' => ['A', str_repeat('あ', 101)],
                ],
            ],
        ]));

        $response->assertSessionHasErrors(['questions.0.options.1' => '選択肢は 100 文字以内で入力してください']);
        $this->assertDatabaseCount('surveys', 0);
    }

    /**
     * PU-104-inp: アンケート編集(説明空上書き) — 再表示で説明が空
     */
    public function test_update_clears_description_when_empty(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();
        $survey = Survey::factory()->create([
            'company_id' => $company->id,
            'description' => '既存の説明',
        ]);
        $question = Question::factory()->create(['survey_id' => $survey->id]);
        QuestionOption::factory()->count(2)->sequence(
            ['sort_order' => 1, 'label' => 'A'],
            ['sort_order' => 2, 'label' => 'B'],
        )->create(['question_id' => $question->id]);

        $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->put(route('surveys.update', $survey), [
                'title' => $survey->title,
                'description' => '',
                'deadline_at' => '',
                'questions' => [
                    [
                        'body' => $question->body,
                        'type' => 'single',
                        'is_required' => '1',
                        'options' => ['A', 'B'],
                    ],
                ],
            ])
            ->assertRedirect(route('surveys.index'));

        $this->assertNull($survey->fresh()->description);

        $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->get(route('surveys.edit', $survey))
            ->assertDontSee('既存の説明', false);
    }

    /**
     * PU-105-inp: アンケート編集(締切空上書き) — 再表示で締切が空（無期限）
     */
    public function test_update_clears_deadline_when_empty(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();
        $survey = Survey::factory()->create([
            'company_id' => $company->id,
            'deadline_at' => now()->addMonth(),
        ]);
        $question = Question::factory()->create(['survey_id' => $survey->id]);
        QuestionOption::factory()->count(2)->sequence(
            ['sort_order' => 1, 'label' => 'A'],
            ['sort_order' => 2, 'label' => 'B'],
        )->create(['question_id' => $question->id]);

        $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->put(route('surveys.update', $survey), [
                'title' => $survey->title,
                'description' => '',
                'deadline_at' => '',
                'questions' => [
                    [
                        'body' => $question->body,
                        'type' => 'single',
                        'is_required' => '1',
                        'options' => ['A', 'B'],
                    ],
                ],
            ])
            ->assertRedirect(route('surveys.index'));

        $this->assertNull($survey->fresh()->deadline_at);
    }

    /**
     * PU-106-inp: アンケート(説明1000文字境界) — 作成成功し管理一覧に表示される
     */
    public function test_store_accepts_description_at_1000_characters(): void
    {
        $description = str_repeat('あ', 1000);
        [$company, $admin, $response] = $this->postSurveyAsAdmin($this->validSurveyPayload([
            'description' => $description,
        ]));

        $response->assertRedirect(route('surveys.index'));
        $this->assertDatabaseHas('surveys', ['company_id' => $company->id, 'description' => $description]);
    }

    /**
     * PU-107-inp: アンケート(設問文500文字境界) — 作成成功し 500 文字の設問文が保存される
     */
    public function test_store_accepts_question_body_at_500_characters(): void
    {
        $body = str_repeat('あ', 500);
        [$company, , $response] = $this->postSurveyAsAdmin($this->validSurveyPayload([
            'questions' => [
                [
                    'body' => $body,
                    'type' => 'single',
                    'is_required' => '1',
                    'options' => ['A', 'B'],
                ],
            ],
        ]));

        $response->assertRedirect(route('surveys.index'));
        $survey = Survey::where('company_id', $company->id)->first();
        $this->assertSame($body, $survey->questions()->first()->body);
    }

    /**
     * PU-108-inp: アンケート(選択肢100文字境界) — 作成成功する
     */
    public function test_store_accepts_option_label_at_100_characters(): void
    {
        $label = str_repeat('あ', 100);
        [$company, , $response] = $this->postSurveyAsAdmin($this->validSurveyPayload([
            'questions' => [
                [
                    'body' => '設問1',
                    'type' => 'single',
                    'is_required' => '1',
                    'options' => ['A', $label],
                ],
            ],
        ]));

        $response->assertRedirect(route('surveys.index'));
        $survey = Survey::where('company_id', $company->id)->first();
        $this->assertTrue($survey->questions()->first()->options()->where('label', $label)->exists());
    }

    /**
     * PU-117-inp: 公開後アンケートの設問変更(HTTP) — 「公開後のアンケートの設問は変更できません」
     */
    public function test_update_rejects_question_changes_for_published_survey_via_http(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();
        $survey = Survey::factory()->published()->create(['company_id' => $company->id]);
        $question = Question::factory()->create([
            'survey_id' => $survey->id,
            'body' => '元の設問',
        ]);
        QuestionOption::factory()->count(2)->sequence(
            ['sort_order' => 1, 'label' => 'A'],
            ['sort_order' => 2, 'label' => 'B'],
        )->create(['question_id' => $question->id]);

        $response = $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->put(route('surveys.update', $survey), [
                'title' => $survey->title,
                'description' => $survey->description ?? '',
                'deadline_at' => '',
                'questions' => [
                    [
                        'body' => '変更後の設問',
                        'type' => 'single',
                        'is_required' => '1',
                        'options' => ['X', 'Y'],
                    ],
                ],
            ]);

        $response->assertSessionHasErrors(['questions' => '公開後のアンケートの設問は変更できません']);
        $this->assertDatabaseHas('questions', ['id' => $question->id, 'body' => '元の設問']);
    }
}
