<?php

namespace Tests\Feature\Services;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Survey;
use App\Models\User;
use App\Services\SurveyAnswerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SurveyAnswerServiceTest extends TestCase
{
    use RefreshDatabase;

    private SurveyAnswerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SurveyAnswerService();
    }

    /**
     * @return array{0: Survey, 1: Question, 2: QuestionOption, 3: QuestionOption, 4: User}
     */
    private function makePublishedSurveyWithSingleQuestion(): array
    {
        $survey = Survey::factory()->published()->create();
        $question = Question::factory()->create(['survey_id' => $survey->id]);
        $option1 = QuestionOption::factory()->create(['question_id' => $question->id, 'label' => '満足', 'sort_order' => 1]);
        $option2 = QuestionOption::factory()->create(['question_id' => $question->id, 'label' => '不満', 'sort_order' => 2]);
        $user = User::factory()->create(['company_id' => $survey->company_id]);

        return [$survey, $question, $option1, $option2, $user];
    }

    /**
     * PU-027-evt: submit(初回) — survey_responses 1 件・survey_answers 1 件が作成され submitted_at が設定される
     */
    public function test_submit_creates_response_and_answers(): void
    {
        [$survey, $question, $option1, , $user] = $this->makePublishedSurveyWithSingleQuestion();

        $response = $this->service->submit($user, $survey, [
            $question->id => $option1->id,
        ]);

        $this->assertSame(1, $survey->responses()->count());
        $this->assertNotNull($response->submitted_at);
        $this->assertDatabaseHas('survey_answers', [
            'survey_response_id' => $response->id,
            'question_id' => $question->id,
            'question_option_id' => $option1->id,
        ]);
        $this->assertSame(1, $response->answers()->count());
    }

    /**
     * PU-028-evt: submit(修正) — survey_responses は 1 件のまま answers が新しい選択肢 1 件に洗い替えられる
     */
    public function test_submit_replaces_answers_on_resubmission(): void
    {
        [$survey, $question, $option1, $option2, $user] = $this->makePublishedSurveyWithSingleQuestion();

        $this->service->submit($user, $survey, [$question->id => $option1->id]);
        $response = $this->service->submit($user, $survey, [$question->id => $option2->id]);

        $this->assertSame(1, $survey->responses()->count());
        $this->assertSame(1, $response->answers()->count());
        $this->assertDatabaseHas('survey_answers', [
            'survey_response_id' => $response->id,
            'question_option_id' => $option2->id,
        ]);
        $this->assertDatabaseMissing('survey_answers', [
            'survey_response_id' => $response->id,
            'question_option_id' => $option1->id,
        ]);
    }

    /**
     * PU-029-evt: submit(終了済み) — ValidationException(VAL-30) が発生し回答が保存されない
     */
    public function test_submit_rejects_closed_survey(): void
    {
        $survey = Survey::factory()->closed()->create();
        $question = Question::factory()->create(['survey_id' => $survey->id]);
        $option = QuestionOption::factory()->create(['question_id' => $question->id]);
        $user = User::factory()->create(['company_id' => $survey->company_id]);

        try {
            $this->service->submit($user, $survey, [$question->id => $option->id]);
            $this->fail('ValidationException が発生しませんでした');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('survey', $e->errors());
        }

        $this->assertSame(0, $survey->responses()->count());
        $this->assertDatabaseCount('survey_answers', 0);
    }
}
