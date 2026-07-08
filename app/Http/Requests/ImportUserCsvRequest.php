<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportUserCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 2MB = 2048KB（VAL-16）
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        $message = 'CSV ファイル(2MB 以内)を選択してください';

        return [
            'file.required' => $message,
            'file.file' => $message,
            'file.mimes' => $message,
            'file.max' => $message,
        ];
    }
}
