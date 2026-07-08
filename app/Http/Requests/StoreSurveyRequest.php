<?php

namespace App\Http\Requests;

use App\Services\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 作成先企業: SU 全体ビューはフォームの企業選択、それ以外は CompanyContext（UC-10）。
     */
    public function targetCompanyId(): ?int
    {
        $actor = $this->user();

        if ($actor->isSuperuser()) {
            $context = app(CompanyContext::class)->current();

            return $context?->id ?? ($this->filled('company_id') ? (int) $this->input('company_id') : null);
        }

        return $actor->company_id;
    }

    public function rules(): array
    {
        return [
            'company_id' => [Rule::requiredIf($this->targetCompanyId() === null), 'nullable', 'exists:companies,id'],
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'deadline_at' => ['nullable', 'date'],
            'questions' => ['required', 'array', 'min:1', 'max:50'],
            'questions.*.body' => ['required', 'string', 'max:500'],
            'questions.*.type' => ['required', Rule::in(['single', 'multiple', 'text'])],
            'questions.*.is_required' => ['nullable', 'boolean'],
            'questions.*.options' => ['exclude_if:questions.*.type,text', 'required', 'array', 'min:2', 'max:10'],
            'questions.*.options.*' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_id.required' => '企業を選択してください',
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
