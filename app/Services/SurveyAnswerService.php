<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SurveyAnswerService
{
    /**
     * 回答の提出・修正（UC-27/28）。
     * 受付外（下書き・終了・締切超過）は VAL-30。
     * survey_responses は updateOrCreate、survey_answers は洗い替え（delete + insert）。
     *
     * @param  array<int, mixed>  $answers  question_id => 値（単一: option id / 複数: option id 配列 / 自由記述: 文字列）
     */
    public function submit(User $user, Survey $survey, array $answers): SurveyResponse
    {
        if (! $survey->isAcceptingAnswers()) {
            throw ValidationException::withMessages([
                'survey' => 'このアンケートは回答を受け付けていません',
            ]);
        }

        return DB::transaction(function () use ($user, $survey, $answers) {
            $response = SurveyResponse::updateOrCreate(
                ['survey_id' => $survey->id, 'user_id' => $user->id],
                ['submitted_at' => now()],
            );
            $response->touch();

            $response->answers()->delete();

            foreach ($survey->questions as $question) {
                $value = $answers[$question->id] ?? null;

                if ($value === null || $value === '' || $value === []) {
                    continue;
                }

                match ($question->type) {
                    QuestionType::Single => $response->answers()->create([
                        'question_id' => $question->id,
                        'question_option_id' => (int) $value,
                    ]),
                    QuestionType::Multiple => collect($value)->each(
                        fn ($optionId) => $response->answers()->create([
                            'question_id' => $question->id,
                            'question_option_id' => (int) $optionId,
                        ]),
                    ),
                    QuestionType::Text => $response->answers()->create([
                        'question_id' => $question->id,
                        'text_value' => $value,
                    ]),
                };
            }

            return $response;
        });
    }
}
