@extends('layouts.app')

@section('title', 'ユーザー管理')

@section('content')
<h1 class="text-xl font-bold">ユーザー管理</h1>

<div class="rounded border border-gray-200 bg-white p-4">
    <h2 class="mb-3 font-semibold">ユーザー登録</h2>
    <form method="POST" action="{{ route('users.store') }}" class="grid grid-cols-2 gap-4 lg:grid-cols-3">
        @csrf

        @if ($companies !== null)
            <div>
                <label for="company_id" class="mb-1 block text-sm font-medium">企業</label>
                <select id="company_id" name="company_id" class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                    <option value="">選択してください</option>
                    @foreach ($companies as $c)
                        <option value="{{ $c->id }}" @selected(old('company_id') == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div>
            <label for="role" class="mb-1 block text-sm font-medium">ロール</label>
            <select id="role" name="role" class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                <option value="user" @selected(old('role', 'user') === 'user')>ユーザー</option>
                <option value="admin" @selected(old('role') === 'admin')>管理者</option>
            </select>
        </div>

        <div>
            <label for="name" class="mb-1 block text-sm font-medium">氏名</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}"
                   class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
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

        @if ($departments !== null)
            <div>
                <label for="department_id" class="mb-1 block text-sm font-medium">部署</label>
                <select id="department_id" name="department_id" class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                    <option value="">未設定</option>
                    @foreach ($departments as $d)
                        <option value="{{ $d->id }}" @selected(old('department_id') == $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div>
            <label for="gender" class="mb-1 block text-sm font-medium">性別</label>
            <select id="gender" name="gender" class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                @foreach (\App\Enums\Gender::cases() as $g)
                    <option value="{{ $g->value }}" @selected(old('gender', 'no_answer') === $g->value)>{{ $g->label() }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="birth_date" class="mb-1 block text-sm font-medium">生年月日</label>
            <input type="date" id="birth_date" name="birth_date" value="{{ old('birth_date') }}"
                   class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label for="hired_month" class="mb-1 block text-sm font-medium">入社年月</label>
            <input type="month" id="hired_month" name="hired_month" value="{{ old('hired_month') }}"
                   class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
        </div>

        <div class="flex items-end">
            <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                登録
            </button>
        </div>
    </form>
</div>

<div class="rounded border border-gray-200 bg-white p-4">
    <h2 class="mb-3 font-semibold">ユーザー一覧</h2>
    <table class="w-full text-left text-sm">
        <thead>
        <tr class="border-b text-gray-500">
            <th class="py-2">氏名</th>
            <th class="py-2">メールアドレス</th>
            @if ($company === null)
                <th class="py-2">企業</th>
            @endif
            <th class="py-2">ロール</th>
            <th class="py-2">部署</th>
            <th class="py-2">性別</th>
            <th class="py-2"></th>
        </tr>
        </thead>
        <tbody>
        @foreach ($users as $user)
            <tr class="border-b" data-testid="user-row-{{ $user->id }}">
                <td class="py-2">{{ $user->name }}</td>
                <td class="py-2">{{ $user->email }}</td>
                @if ($company === null)
                    <td class="py-2">{{ $user->company?->name }}</td>
                @endif
                <td class="py-2">{{ $user->role->label() }}</td>
                <td class="py-2">{{ $user->department?->name ?? '未設定' }}</td>
                <td class="py-2">{{ $user->gender->label() }}</td>
                <td class="py-2 text-right space-x-2">
                    <a href="{{ route('users.edit', $user) }}" class="text-blue-600 hover:underline">編集</a>
                    <form method="POST" action="{{ route('users.destroy', $user) }}" class="inline"
                          data-confirm="ユーザー「{{ $user->name }}」を削除しますか？">
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
