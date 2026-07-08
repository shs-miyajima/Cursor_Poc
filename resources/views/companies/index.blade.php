@extends('layouts.app')

@section('title', '企業管理')

@section('content')
<h1 class="text-xl font-bold">企業管理</h1>

<div class="rounded border border-gray-200 bg-white p-4">
    <h2 class="mb-3 font-semibold">企業登録</h2>
    <form method="POST" action="{{ route('companies.store') }}" class="flex flex-wrap items-end gap-4">
        @csrf
        <div>
            <label for="name" class="mb-1 block text-sm font-medium">企業名</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}"
                   class="w-64 rounded border border-gray-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label for="code" class="mb-1 block text-sm font-medium">企業コード</label>
            <input type="text" id="code" name="code" value="{{ old('code') }}"
                   class="w-48 rounded border border-gray-300 px-3 py-2 text-sm">
        </div>
        <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
            登録
        </button>
    </form>
</div>

<div class="rounded border border-gray-200 bg-white p-4">
    <h2 class="mb-3 font-semibold">企業一覧</h2>
    <table class="w-full text-left text-sm">
        <thead>
        <tr class="border-b text-gray-500">
            <th class="py-2">企業名</th>
            <th class="py-2">企業コード</th>
            <th class="py-2"></th>
        </tr>
        </thead>
        <tbody>
        @foreach ($companies as $company)
            <tr class="border-b" data-testid="company-row-{{ $company->id }}">
                <td class="py-2">{{ $company->name }}</td>
                <td class="py-2">{{ $company->code }}</td>
                <td class="py-2 text-right space-x-2">
                    <a href="{{ route('companies.edit', $company) }}" class="text-blue-600 hover:underline">編集</a>
                    <form method="POST" action="{{ route('companies.destroy', $company) }}" class="inline"
                          data-confirm="企業「{{ $company->name }}」を削除しますか？">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600 hover:underline">削除</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
