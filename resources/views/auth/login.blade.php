@extends('layouts.app')

@section('title', 'ログイン')

@section('content')
<div class="mx-auto mt-10 max-w-md rounded border border-gray-200 bg-white p-8 shadow-sm">
    <h1 class="mb-6 text-xl font-bold">ログイン</h1>

    <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4">
        @csrf

        <div>
            <label for="company_code" class="mb-1 block text-sm font-medium">企業コード</label>
            <input type="text" id="company_code" name="company_code" value="{{ old('company_code') }}"
                   class="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                   placeholder="スーパーユーザーは空欄">
        </div>

        <div>
            <label for="email" class="mb-1 block text-sm font-medium">メールアドレス</label>
            <input type="text" id="email" name="email" value="{{ old('email') }}"
                   class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium">パスワード</label>
            <input type="password" id="password" name="password"
                   class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
        </div>

        <button type="submit" class="w-full rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
            ログイン
        </button>
    </form>
</div>
@endsection
