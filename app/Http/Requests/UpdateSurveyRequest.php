<?php

namespace App\Http\Requests;

use App\Enums\SurveyStatus;
use App\Models\Survey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Survey $survey */
        $survey = $this->route('survey');

        $rules = [
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'deadline_at' => ['nullable', 'date'],
        ];

        // 設問の編集は下書きのみ（VAL-27。公開後の編集フォームは設問を送信しない）
        if ($survey->status === SurveyStatus::Draft) {
            $rules += [
                'questions' => ['required', 'array', 'min:1', 'max:50'],
                'questions.*.body' => ['required', 'string', 'max:500'],
                'questions.*.type' => ['required', Rule::in(['single', 'multiple', 'text'])],
                'questions.*.is_required' => ['nullable', 'boolean'],
                'questions.*.options' => ['exclude_if:questions.*.type,text', 'required', 'array', 'min:2', 'max:10'],
                'questions.*.options.*' => ['required', 'string', 'max:100'],
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'title.required' => 'タイトルは必須です',
            'title.max' => 'タイトルは 100 文字以内で入力してください',
            'description.max' => '説明は 1000 文字以内で入力してください',
            'questions.required' => '設問を 1 問以上追加してください',
            'questions.min' => '設問を 1 問以上追加してください',
            'questions.max' => '設問は 50 問以内にしてください',
            'questions.*.body.required' => '設問文は必須です',
            'questions.*.body.max' => '設問文は 500 文字以内で入力してください',
            'questions.*.options.required' => '選択肢は 2 個以上 10 個以内で入力してください',
            'questions.*.options.min' => '選択肢は 2 個以上 10 個以内で入力してください',
            'questions.*.options.max' => '選択肢は 2 個以上 10 個以内で入力してください',
            'questions.*.options.*.required' => '選択肢は必須です',
            'questions.*.options.*.max' => '選択肢は 100 文字以内で入力してください',
        ];
    }
}
