@extends('layouts.app')

@section('title', 'CSV 取込確認')

@section('content')
<h1 class="text-xl font-bold">CSV 取込確認</h1>

<div class="rounded border border-gray-200 bg-white p-4 space-y-4">
    <p class="text-sm" data-testid="import-summary">新規 {{ $newCount }} 件・更新 {{ $updateCount }} 件</p>

    <table class="w-full text-left text-sm">
        <thead>
        <tr class="border-b text-gray-500">
            <th class="py-2">行</th>
            <th class="py-2">区分</th>
            <th class="py-2">氏名</th>
            <th class="py-2">メールアドレス</th>
            <th class="py-2">部署</th>
            <th class="py-2">性別</th>
            <th class="py-2">生年月日</th>
            <th class="py-2">入社年月</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($rows as $row)
            <tr class="border-b" data-testid="import-row-{{ $row->line }}">
                <td class="py-2">{{ $row->line }}</td>
                <td class="py-2">
                    @if ($row->isUpdate)
                        <span class="rounded bg-amber-100 px-2 py-0.5 text-amber-800">更新</span>
                    @else
                        <span class="rounded bg-green-100 px-2 py-0.5 text-green-800">新規</span>
                    @endif
                </td>
                <td class="py-2">{{ $row->name }}</td>
                <td class="py-2">{{ $row->email }}</td>
                <td class="py-2">{{ $row->departmentName ?? '未設定' }}</td>
                <td class="py-2">{{ \App\Enums\Gender::from($row->gender)->label() }}</td>
                <td class="py-2">{{ $row->birthDate }}</td>
                <td class="py-2">{{ $row->hiredMonth !== null ? substr($row->hiredMonth, 0, 7) : '' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="flex gap-3">
        <form method="POST" action="{{ route('users.import.commit') }}">
            @csrf
            <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                確定
            </button>
        </form>
        <a href="{{ route('users.import') }}" class="rounded border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">
            戻る
        </a>
    </div>
</div>
@endsection
