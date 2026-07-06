<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListEquipmentLoansRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mock_user_id' => ['required', 'integer'],
            'search' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'mock_user_id.required' => '操作ユーザーを選択してください',
        ];
    }
}
