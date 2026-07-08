@extends('layouts.app')

@section('title', '企業編集')

@section('content')
<h1 class="text-xl font-bold">企業編集</h1>

<div class="max-w-lg rounded border border-gray-200 bg-white p-6">
    <form method="POST" action="{{ route('companies.update', $company) }}" class="space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label class="mb-1 block text-sm font-medium">企業コード</label>
            {{-- 企業コードは変更不可（S-02a） --}}
            <input type="text" value="{{ $company->code }}" disabled
                   class="w-full rounded border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-500">
        </div>

        <div>
            <label for="name" class="mb-1 block text-sm font-medium">企業名</label>
            <input type="text" id="name" name="name" value="{{ old('name', $company->name) }}"
                   class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
        </div>

        <div class="flex gap-3">
            <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                保存
            </button>
            <a href="{{ route('companies.index') }}" class="rounded border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">
                戻る
            </a>
        </div>
    </form>
</div>
@endsection
