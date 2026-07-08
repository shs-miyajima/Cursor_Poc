<?php

namespace App\Http\Controllers;

use App\Enums\SurveyStatus;
use App\Http\Requests\SubmitAnswerRequest;
use App\Models\Survey;
use App\Services\SurveyAnswerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MySurveyController extends Controller
{
    /**
     * S-09: 未回答 / 回答済に分けて表示（UC-27〜29。下書きは表示しない）。
     */
    public function index(): View
    {
        $user = auth()->user();

        $surveys = Survey::where('company_id', $user->company_id)
            ->whereNot('status', SurveyStatus::Draft)
            ->orderByDesc('created_at')
            ->get();

        $answeredIds = $user->surveyResponses()->pluck('survey_id')->all();

        return view('my.surveys.index', [
            'unanswered' => $surveys->filter(fn ($s) => ! in_array($s->id, $answeredIds, true))->values(),
            'answered' => $surveys->filter(fn ($s) => in_array($s->id, $answeredIds, true))->values(),
        ]);
    }

    /**
     * S-10: 回答画面。下書き・他社は 404（AC-19 / NFR-04）。
     */
    public function show(Survey $survey): View
    {
        $this->authorizeAnswerable($survey);

        $user = auth()->user();

        $response = $user->surveyResponses()->where('survey_id', $survey->id)->first();

        // question_id => 回答値（単一: option id / 複数: option id 配列 / 自由記述: 文字列）
        $previous = [];
        if ($response !== null) {
            foreach ($response->answers as $answer) {
                $question = $survey->questions->firstWhere('id', $answer->question_id);
                if ($question === null) {
                    continue;
                }
                if ($question->type->value === 'multiple') {
                    $previous[$answer->question_id][] = $answer->question_option_id;
                } elseif ($question->type->value === 'single') {
                    $previous[$answer->question_id] = $answer->question_option_id;
                } else {
                    $previous[$answer->question_id] = $answer->text_value;
                }
            }
        }

        return view('my.surveys.show', [
            'survey' => $survey->load('questions.options'),
            'accepting' => $survey->isAcceptingAnswers(),
            'hasResponse' => $response !== null,
            'previous' => $previous,
        ]);
    }

    public function answer(SubmitAnswerRequest $request, Survey $survey, SurveyAnswerService $service): RedirectResponse
    {
        $this->authorizeAnswerable($survey);

        $service->submit($request->user(), $survey, $request->input('answers', []));

        return redirect()->route('my.surveys.index')->with('success', '回答を送信しました');
    }

    private function authorizeAnswerable(Survey $survey): void
    {
        abort_if($survey->company_id !== auth()->user()->company_id, 404);
        abort_if($survey->status === SurveyStatus::Draft, 404);
    }
}
