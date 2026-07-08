<?php

namespace Tests\Feature\Services;

use App\Enums\SurveyStatus;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Survey;
use App\Services\SurveyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SurveyServiceTest extends TestCase
{
    use RefreshDatabase;

    private SurveyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SurveyService();
    }

    /**
     * PU-021-evt: SurveyService::publish(過去締切) — ValidationException(VAL-26) が発生し status は draft のまま
     */
    public function test_publish_rejects_past_deadline(): void
    {
        $survey = Survey::factory()->create([
            'deadline_at' => now()->subDay(),
        ]);

        try {
            $this->service->publish($survey);
            $this->fail('ValidationException が発生しませんでした');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('deadline_at', $e->errors());
        }

        $this->assertDatabaseHas('surveys', ['id' => $survey->id, 'status' => 'draft']);
    }

    /**
     * PU-022-evt: SurveyService::update(公開後の設問変更) — ValidationException(VAL-27) が発生し設問が変更されない
     */
    public function test_update_rejects_question_changes_for_published_survey(): void
    {
        $survey = Survey::factory()->published()->create();
        $question = Question::factory()->create([
            'survey_id' => $survey->id,
            'body' => '元の設問',
        ]);
        QuestionOption::factory()->create(['question_id' => $question->id, 'label' => 'A']);

        try {
            $this->service->update($survey, [
                'title' => $survey->title,
                'questions' => [
                    ['body' => '変更後の設問', 'type' => 'single', 'options' => ['X', 'Y']],
                ],
            ]);
            $this->fail('ValidationException が発生しませんでした');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('questions', $e->errors());
        }

        $this->assertDatabaseHas('questions', ['id' => $question->id, 'body' => '元の設問']);
        $this->assertSame(1, $survey->questions()->count());
    }

    /**
     * PU-023-evt: SurveyService::update(下書きの設問洗い替え) — questions が 1 件になり内容が新しい設問文になる
     */
    public function test_update_replaces_questions_for_draft_survey(): void
    {
        $survey = Survey::factory()->create();
        Question::factory()->count(2)->sequence(
            ['sort_order' => 1],
            ['sort_order' => 2],
        )->create(['survey_id' => $survey->id]);

        $this->service->update($survey, [
            'title' => $survey->title,
            'questions' => [
                [
                    'body' => '新しい設問',
                    'type' => 'single',
                    'is_required' => true,
                    'options' => ['A', 'B'],
                ],
            ],
        ]);

        $questions = $survey->refresh()->questions;

        $this->assertCount(1, $questions);
        $this->assertSame('新しい設問', $questions[0]->body);
        $this->assertTrue($questions[0]->is_required);
        $this->assertSame(['A', 'B'], $questions[0]->options->pluck('label')->all());
    }

    /**
     * PU-024-other: Survey::effectiveStatus(締切超過) — closed が返り DB の status は published のまま
     */
    public function test_effective_status_is_closed_after_deadline(): void
    {
        $survey = Survey::factory()->published()->create([
            'deadline_at' => now()->subMinute(),
        ]);

        $this->assertSame(SurveyStatus::Closed, $survey->effectiveStatus());
        $this->assertDatabaseHas('surveys', ['id' => $survey->id, 'status' => 'published']);
    }

    /**
     * PU-025-other: Survey::effectiveStatus(締切未来) — published が返る
     */
    public function test_effective_status_is_published_before_deadline(): void
    {
        $survey = Survey::factory()->published()->create([
            'deadline_at' => now()->addDay(),
        ]);

        $this->assertSame(SurveyStatus::Published, $survey->effectiveStatus());
    }

    /**
     * PU-026-other: Survey::effectiveStatus(締切なし) — published が返る（無期限）
     */
    public function test_effective_status_is_published_without_deadline(): void
    {
        $survey = Survey::factory()->published()->create([
            'deadline_at' => null,
        ]);

        $this->assertSame(SurveyStatus::Published, $survey->effectiveStatus());
    }
}
