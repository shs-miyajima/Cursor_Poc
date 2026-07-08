<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\Survey;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SurveyResultService
{
    /**
     * クエリパラメータをフィルタ DTO に変換する（年代は生年月日範囲に変換して aggregate 側で使用）。
     */
    public function buildFilter(array $query): ResultFilter
    {
        $date = fn (?string $v) => ($v !== null && $v !== '') ? CarbonImmutable::parse($v) : null;
        $month = fn (?string $v) => ($v !== null && $v !== '') ? CarbonImmutable::parse($v.'-01') : null;

        return new ResultFilter(
            departmentId: isset($query['department_id']) && $query['department_id'] !== ''
                ? (int) $query['department_id'] : null,
            dateFrom: $date($query['date_from'] ?? null),
            dateTo: $date($query['date_to'] ?? null),
            gender: ($query['gender'] ?? '') !== '' ? $query['gender'] : null,
            ageGroup: ($query['age_group'] ?? '') !== '' ? $query['age_group'] : null,
            hiredFrom: $month($query['hired_from'] ?? null),
            hiredTo: $month($query['hired_to'] ?? null),
        );
    }

    /**
     * 絞り込み条件で回答を絞り、設問ごとの集計を返す（UC-24/25、NFR-06）。
     * 論理削除済みユーザーの回答も集計に含める（AC-14。クエリビルダの join は SoftDeletes の影響を受けない）。
     * 属性未設定（NULL）は、当該属性で絞り込んだ場合は集計対象外、絞り込みなしなら含まれる。
     */
    public function aggregate(Survey $survey, ResultFilter $filter): array
    {
        $responses = DB::table('survey_responses')
            ->join('users', 'users.id', '=', 'survey_responses.user_id')
            ->where('survey_responses.survey_id', $survey->id);

        if ($filter->departmentId !== null) {
            $responses->where('users.department_id', $filter->departmentId);
        }

        if ($filter->gender !== null) {
            $responses->where('users.gender', $filter->gender);
        }

        if ($filter->ageGroup !== null) {
            ['from' => $from, 'to' => $to] = AgeGroupResolver::rangeFor($filter->ageGroup, now());
            $responses->whereNotNull('users.birth_date');
            // whereDate で日付部分のみ比較する（sqlite は datetime 文字列で保存されるため）
            if ($from !== null) {
                $responses->whereDate('users.birth_date', '>=', $from->toDateString());
            }
            if ($to !== null) {
                $responses->whereDate('users.birth_date', '<=', $to->toDateString());
            }
        }

        if ($filter->hiredFrom !== null) {
            $responses->whereDate('users.hired_month', '>=', $filter->hiredFrom->toDateString());
        }

        if ($filter->hiredTo !== null) {
            $responses->whereDate('users.hired_month', '<=', $filter->hiredTo->endOfMonth()->toDateString());
        }

        if ($filter->dateFrom !== null) {
            $responses->where('survey_responses.updated_at', '>=', $filter->dateFrom->startOfDay());
        }

        if ($filter->dateTo !== null) {
            $responses->where('survey_responses.updated_at', '<=', $filter->dateTo->endOfDay());
        }

        $responseIds = $responses->pluck('survey_responses.id');

        // 設問 × 選択肢の件数を 1 クエリで集計する（PHP 側でループ集計しない = NFR-06）
        $counts = DB::table('survey_answers')
            ->whereIn('survey_response_id', $responseIds)
            ->whereNotNull('question_option_id')
            ->groupBy('question_id', 'question_option_id')
            ->select('question_id', 'question_option_id', DB::raw('count(*) as cnt'))
            ->get()
            ->groupBy('question_id');

        $questions = [];

        foreach ($survey->questions()->with('options')->get() as $question) {
            if ($question->type === QuestionType::Text) {
                $questions[] = [
                    'id' => $question->id,
                    'body' => $question->body,
                    'type' => $question->type->value,
                    'required' => $question->is_required,
                    'answers' => DB::table('survey_answers')
                        ->whereIn('survey_response_id', $responseIds)
                        ->where('question_id', $question->id)
                        ->whereNotNull('text_value')
                        ->orderBy('id')
                        ->pluck('text_value')
                        ->all(),
                ];

                continue;
            }

            $questionCounts = $counts->get($question->id, collect())
                ->keyBy('question_option_id');

            $questions[] = [
                'id' => $question->id,
                'body' => $question->body,
                'type' => $question->type->value,
                'required' => $question->is_required,
                'options' => $question->options->map(fn ($option) => [
                    'id' => $option->id,
                    'label' => $option->label,
                    'count' => (int) ($questionCounts->get($option->id)?->cnt ?? 0),
                ])->all(),
            ];
        }

        return [
            'survey' => [
                'id' => $survey->id,
                'title' => $survey->title,
            ],
            'total_responses' => $responseIds->count(),
            'questions' => $questions,
        ];
    }
}
