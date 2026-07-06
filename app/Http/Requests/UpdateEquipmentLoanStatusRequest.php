<?php

namespace App\Http\Requests;

use App\Enums\EquipmentLoanStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEquipmentLoanStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mock_user_id' => ['required', 'integer'],
            'status' => ['required', Rule::in(EquipmentLoanStatus::updatableValues())],
        ];
    }

    public function messages(): array
    {
        return [
            'mock_user_id.required' => '操作ユーザーを選択してください',
            'status.required' => '更新するステータスを指定してください',
            'status.in' => '指定されたステータスは更新できません',
        ];
    }
}
