<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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

    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'email',
                Rule::unique('users', 'email')
                    ->ignore($target->id)
                    ->where('company_id', $target->company_id)
                    ->whereNull('deleted_at'),
            ],
            // 空欄はパスワード変更なし（画面 S-04a）
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'department_id' => [
                'nullable',
                Rule::exists('departments', 'id')->where('company_id', $target->company_id)->whereNull('deleted_at'),
            ],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other', 'no_answer'])],
            'birth_date' => ['nullable', 'date_format:Y-m-d'],
            'hired_month' => ['nullable', 'date_format:Y-m'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '氏名は必須です',
            'name.max' => '氏名は 100 文字以内で入力してください',
            'email.required' => 'メールアドレスは必須です',
            'email.email' => 'メールアドレスの形式が正しくありません',
            'email.unique' => 'このメールアドレスは既に登録されています',
            'password.min' => 'パスワードは 8 文字以上で入力してください',
            'password.max' => 'パスワードは 255 文字以内で入力してください',
        ];
    }
}
