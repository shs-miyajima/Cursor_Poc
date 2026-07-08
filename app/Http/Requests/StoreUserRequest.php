<?php

namespace App\Http\Requests;

use App\Services\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('email')) {
            $this->merge(['email' => mb_strtolower($this->input('email'))]);
        }
    }

    /**
     * 登録先企業: SU 全体ビューはフォームの企業選択、それ以外は CompanyContext。
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
        $companyId = $this->targetCompanyId();

        return [
            'company_id' => [Rule::requiredIf($companyId === null), 'nullable', 'exists:companies,id'],
            'role' => ['required', Rule::in(['admin', 'user'])],
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'email',
                Rule::unique('users', 'email')
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at'),
            ],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'department_id' => [
                'nullable',
                Rule::exists('departments', 'id')->where('company_id', $companyId)->whereNull('deleted_at'),
            ],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other', 'no_answer'])],
            'birth_date' => ['nullable', 'date_format:Y-m-d'],
            'hired_month' => ['nullable', 'date_format:Y-m'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_id.required' => '企業を選択してください',
            'name.required' => '氏名は必須です',
            'name.max' => '氏名は 100 文字以内で入力してください',
            'email.required' => 'メールアドレスは必須です',
            'email.email' => 'メールアドレスの形式が正しくありません',
            'email.unique' => 'このメールアドレスは既に登録されています',
            'password.required' => 'パスワードは必須です',
            'password.min' => 'パスワードは 8 文字以上で入力してください',
            'password.max' => 'パスワードは 255 文字以内で入力してください',
        ];
    }
}
