<?php

namespace App\Http\Requests;

use App\Services\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $company = app(CompanyContext::class)->requireCompany();

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('departments', 'name')
                    ->where('company_id', $company->id)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '部署名は必須です',
            'name.max' => '部署名は 100 文字以内で入力してください',
            'name.unique' => 'この部署名は既に登録されています',
        ];
    }
}
