@extends('layouts.app')

@section('title', '部署管理')

@section('content')
<h1 class="text-xl font-bold">部署管理</h1>

<div class="rounded border border-gray-200 bg-white p-4">
    <h2 class="mb-3 font-semibold">部署登録</h2>
    <form method="POST" action="{{ route('departments.store') }}" class="flex items-end gap-4">
        @csrf
        <div>
            <label for="name" class="mb-1 block text-sm font-medium">部署名</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}"
                   class="w-64 rounded border border-gray-300 px-3 py-2 text-sm">
        </div>
        <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
            登録
        </button>
    </form>
</div>

<div class="rounded border border-gray-200 bg-white p-4">
    <h2 class="mb-3 font-semibold">部署一覧</h2>
    <table class="w-full text-left text-sm">
        <thead>
        <tr class="border-b text-gray-500">
            <th class="py-2">部署名</th>
            <th class="py-2"></th>
        </tr>
        </thead>
        <tbody>
        @foreach ($departments as $department)
            <tr class="border-b" data-testid="department-row-{{ $department->id }}">
                <td class="py-2">{{ $department->name }}</td>
                <td class="py-2 text-right space-x-2">
                    <a href="{{ route('departments.edit', $department) }}" class="text-blue-600 hover:underline">編集</a>
                    <form method="POST" action="{{ route('departments.destroy', $department) }}" class="inline"
                          data-confirm="部署「{{ $department->name }}」を削除しますか？">
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
