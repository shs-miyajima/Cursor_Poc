<?php

namespace Tests\Feature\Http;

use App\Models\Question;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Http\Concerns\HttpTestHelpers;
use Tests\TestCase;

class SubmitAnswerHttpTest extends TestCase
{
    use HttpTestHelpers;
    use RefreshDatabase;

    /**
     * @return array{0: \App\Models\Company, 1: Survey, 2: Question, 3: User}
     */
    private function createOptionalTextSurvey(): array
    {
        [$company, $admin] = $this->createCompanyWithAdmin();
        $survey = Survey::factory()->published()->create(['company_id' => $company->id]);
        $question = Question::factory()->text()->create([
            'survey_id' => $survey->id,
            'is_required' => false,
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);

        return [$company, $survey, $question, $user];
    }

    /**
     * PU-100-inp: 回答(任意設問未回答) — 送信成功し回答済一覧へ遷移
     */
    public function test_answer_accepts_empty_optional_text_question(): void
    {
        [, $survey, $question, $user] = $this->createOptionalTextSurvey();

        $response = $this->actingAs($user)
            ->post(route('my.surveys.answer', $survey), [
                'answers' => [
                    $question->id => '',
                ],
            ]);

        $response->assertRedirect(route('my.surveys.index'));
        $this->assertDatabaseHas('survey_responses', [
            'survey_id' => $survey->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * PU-101-inp: 回答(自由記述1001文字) — 「1000 文字以内で入力してください」
     */
    public function test_answer_rejects_text_over_1000_characters(): void
    {
        [, $survey, $question, $user] = $this->createOptionalTextSurvey();

        $response = $this->actingAs($user)
            ->post(route('my.surveys.answer', $survey), [
                'answers' => [
                    $question->id => str_repeat('あ', 1001),
                ],
            ]);

        $response->assertSessionHasErrors(["answers.{$question->id}"]);
        $errors = session('errors')->get("answers.{$question->id}");
        $this->assertStringContainsString('1000 文字以内で入力してください', $errors[0]);
        $this->assertDatabaseCount('survey_responses', 0);
    }

    /**
     * PU-102-inp: 回答(自由記述1000文字境界) — 送信成功し 1000 文字が保持される
     */
    public function test_answer_accepts_text_at_1000_characters(): void
    {
        [, $survey, $question, $user] = $this->createOptionalTextSurvey();
        $text = str_repeat('あ', 1000);

        $this->actingAs($user)
            ->post(route('my.surveys.answer', $survey), [
                'answers' => [
                    $question->id => $text,
                ],
            ])
            ->assertRedirect(route('my.surveys.index'));

        $this->actingAs($user)
            ->get(route('my.surveys.show', $survey))
            ->assertSee($text, false);
    }
}
