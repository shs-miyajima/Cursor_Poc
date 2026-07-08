<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Enums\SurveyStatus;
use App\Models\Company;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SurveyService
{
    /**
     * 設問・選択肢込みで作成する。$publish 時は締切チェック（VAL-26）。
     */
    public function create(Company $company, User $creator, array $data, bool $publish): Survey
    {
        if ($publish) {
            $this->assertDeadlineIsFuture($data['deadline_at'] ?? null);
        }

        return DB::transaction(function () use ($company, $creator, $data, $publish) {
            $survey = Survey::create([
                'company_id' => $company->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => $publish ? SurveyStatus::Published : SurveyStatus::Draft,
                'deadline_at' => $data['deadline_at'] ?? null,
                'created_by' => $creator->id,
            ]);

            $this->createQuestions($survey, $data['questions']);

            return $survey;
        });
    }

    /**
     * 下書き: 設問洗い替え可。公開後: タイトル・説明・締切のみ（設問変更は VAL-27）。
     */
    public function update(Survey $survey, array $data): Survey
    {
        if ($survey->status !== SurveyStatus::Draft && array_key_exists('questions', $data)) {
            throw ValidationException::withMessages([
                'questions' => '公開後のアンケートの設問は変更できません',
            ]);
        }

        return DB::transaction(function () use ($survey, $data) {
            $survey->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'deadline_at' => $data['deadline_at'] ?? null,
            ]);

            if ($survey->status === SurveyStatus::Draft && array_key_exists('questions', $data)) {
                // 下書き中は設問を洗い替える
                foreach ($survey->questions as $question) {
                    $question->options()->delete();
                }
                $survey->questions()->delete();
                $this->createQuestions($survey, $data['questions']);
            }

            return $survey->refresh();
        });
    }

    /**
     * 下書き → 公開（VAL-26 の締切チェック含む）。
     */
    public function publish(Survey $survey): void
    {
        $this->assertDeadlineIsFuture($survey->deadline_at?->toDateTimeString());

        $survey->update(['status' => SurveyStatus::Published]);
    }

    /**
     * 公開 → 終了（UC-21）。
     */
    public function close(Survey $survey): void
    {
        $survey->update(['status' => SurveyStatus::Closed]);
    }

    private function assertDeadlineIsFuture(?string $deadline): void
    {
        if ($deadline !== null && strtotime($deadline) <= time()) {
            throw ValidationException::withMessages([
                'deadline_at' => '締切日時は未来の日時を指定してください',
            ]);
        }
    }

    private function createQuestions(Survey $survey, array $questions): void
    {
        foreach (array_values($questions) as $i => $q) {
            $type = QuestionType::from($q['type']);

            $question = $survey->questions()->create([
                'body' => $q['body'],
                'type' => $type,
                'is_required' => (bool) ($q['is_required'] ?? false),
                'sort_order' => $i + 1,
            ]);

            if ($type->hasOptions()) {
                foreach (array_values($q['options'] ?? []) as $j => $label) {
                    $question->options()->create([
                        'label' => $label,
                        'sort_order' => $j + 1,
                    ]);
                }
            }
        }
    }
}
