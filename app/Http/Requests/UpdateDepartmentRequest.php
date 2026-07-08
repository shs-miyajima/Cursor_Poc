<?php

namespace App\Http\Requests;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Department $target */
        $target = $this->route('department');

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('departments', 'name')
                    ->ignore($target->id)
                    ->where('company_id', $target->company_id)
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
