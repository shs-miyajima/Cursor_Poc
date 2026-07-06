<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEquipmentLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mock_user_id' => ['required', 'integer'],
            'equipment_id' => ['required', 'integer'],
            'requested_from' => ['required', 'date', 'after_or_equal:today'],
            'requested_to' => ['required', 'date', 'after_or_equal:requested_from'],
            'reason' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'mock_user_id.required' => '操作ユーザーを選択してください',
            'equipment_id.required' => '備品を選択してください',
            'requested_from.required' => '貸出開始日を入力してください',
            'requested_from.after_or_equal' => '貸出開始日は本日以降の日付を入力してください',
            'requested_to.required' => '貸出終了日を入力してください',
            'requested_to.after_or_equal' => '貸出終了日は貸出開始日以降の日付を入力してください',
        ];
    }
}
