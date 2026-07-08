<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // 企業コードは変更不可（画面 S-02a）。企業名のみ更新する
        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('companies', 'name')
                    ->ignore($this->route('company'))
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '企業名は必須です',
            'name.max' => '企業名は 100 文字以内で入力してください',
            'name.unique' => 'この企業名は既に登録されています',
        ];
    }
}
