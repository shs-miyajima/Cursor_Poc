<?php

namespace App\Http\Requests;

use App\Enums\QuestionType;
use App\Models\Survey;
use Illuminate\Foundation\Http\FormRequest;

class SubmitAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 設問構成に応じて動的にルールを組み立てる（VAL-28/29）。
     */
    public function rules(): array
    {
        /** @var Survey $survey */
        $survey = $this->route('survey');

        $rules = [];
        $messages = [];

        foreach ($survey->questions as $question) {
            $key = "answers.{$question->id}";

            $fieldRules = [];

            if ($question->is_required) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            if ($question->type === QuestionType::Text) {
                $fieldRules[] = 'string';
                $fieldRules[] = 'max:1000';
            } elseif ($question->type === QuestionType::Multiple) {
                $fieldRules[] = 'array';
            }

            $rules[$key] = $fieldRules;
        }

        return $rules;
    }

    public function messages(): array
    {
        /** @var Survey $survey */
        $survey = $this->route('survey');

        $messages = [];

        foreach ($survey->questions as $question) {
            $messages["answers.{$question->id}.required"] = 'この設問は必須です';
            $messages["answers.{$question->id}.max"] = '1000 文字以内で入力してください';
        }

        return $messages;
    }
}
