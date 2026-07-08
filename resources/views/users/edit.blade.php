@extends('layouts.app')

@section('title', 'ユーザー編集')

@section('content')
<h1 class="text-xl font-bold">ユーザー編集</h1>

<div class="max-w-2xl rounded border border-gray-200 bg-white p-6">
    <form method="POST" action="{{ route('users.update', $user) }}" class="grid grid-cols-2 gap-4">
        @csrf
        @method('PUT')

        <div>
            <label class="mb-1 block text-sm font-medium">所属企業</label>
            {{-- 所属企業・ロールは変更不可（S-04a） --}}
            <input type="text" value="{{ $user->company?->name }}" disabled
                   class="w-full rounded border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-500">
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">ロール</label>
            <input type="text" value="{{ $user->role->label() }}" disabled
                   class="w-full rounded border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-500">
        </div>

        <div>
            <label for="name" class="mb-1 block text-sm font-medium">氏名</label>
            <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}"
                   class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label for="email" class="mb-1 block text-sm font-medium">メールアドレス</label>
            <input type="text" id="email" name="email" value="{{ old('email', $user->email) }}"
                   class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium">パスワード（空欄は変更しない）</label>
            <input type="password" id="password" name="password"
                   class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label for="department_id" class="mb-1 block text-sm font-medium">部署</label>
            <select id="department_id" name="department_id" class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                <option value="">未設定</option>
                @foreach ($departments as $d)
                    <option value="{{ $d->id }}" @selected(old('department_id', $user->department_id) == $d->id)>{{ $d->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="gender" class="mb-1 block text-sm font-medium">性別</label>
            <select id="gender" name="gender" class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                @foreach (\App\Enums\Gender::cases() as $g)
                    <option value="{{ $g->value }}" @selected(old('gender', $user->gender->value) === $g->value)>{{ $g->label() }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="birth_date" class="mb-1 block text-sm font-medium">生年月日</label>
            <input type="date" id="birth_date" name="birth_date"
                   value="{{ old('birth_date', $user->birth_date?->format('Y-m-d')) }}"
                   class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label for="hired_month" class="mb-1 block text-sm font-medium">入社年月</label>
            <input type="month" id="hired_month" name="hired_month"
                   value="{{ old('hired_month', $user->hired_month?->format('Y-m')) }}"
                   class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
        </div>

        <div class="col-span-2 flex gap-3">
            <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                保存
            </button>
            <a href="{{ route('users.index') }}" class="rounded border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">
                戻る
            </a>
        </div>
    </form>
</div>
@endsection
