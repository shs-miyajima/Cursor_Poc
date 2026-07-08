<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('companies', 'name')->whereNull('deleted_at'),
            ],
            'code' => [
                'required', 'string', 'regex:/\A[a-zA-Z0-9]{1,20}\z/',
                Rule::unique('companies', 'code')->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '企業名は必須です',
            'name.max' => '企業名は 100 文字以内で入力してください',
            'name.unique' => 'この企業名は既に登録されています',
            'code.required' => '企業コードは必須です',
            'code.regex' => '企業コードは半角英数字 20 文字以内で入力してください',
            'code.unique' => 'この企業コードは既に登録されています',
        ];
    }
}
