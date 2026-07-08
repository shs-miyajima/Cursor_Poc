<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'アンケート管理') | {{ config('app.name', 'Cursor_Poc') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('scripts')
</head>
<body class="min-h-screen bg-gray-100 text-gray-900">
@php
    $actor = auth()->user();
    $currentCompany = $actor ? app(\App\Services\CompanyContext::class)->current() : null;
@endphp
<header class="bg-slate-800 text-white">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
        <span class="text-lg font-bold">アンケート管理</span>

        @auth
            <nav class="flex flex-wrap items-center gap-4 text-sm" data-testid="nav">
                @if ($actor->isSuperuser())
                    <a href="{{ route('admin.home') }}" class="hover:underline">全体ビュー</a>
                    @if ($currentCompany === null)
                        <a href="{{ route('companies.index') }}" class="hover:underline">企業</a>
                        <a href="{{ route('users.index') }}" class="hover:underline">ユーザー</a>
                        <a href="{{ route('surveys.index') }}" class="hover:underline">アンケート</a>
                    @endif
                @endif

                @if ($currentCompany !== null && ! $actor->isUser())
                    <a href="{{ route('dashboard') }}" class="hover:underline">ダッシュボード</a>
                    <a href="{{ route('surveys.index') }}" class="hover:underline">アンケート</a>
                    <a href="{{ route('users.index') }}" class="hover:underline">ユーザー</a>
                    <a href="{{ route('users.import') }}" class="hover:underline">CSV 登録</a>
                    <a href="{{ route('departments.index') }}" class="hover:underline">部署</a>
                @endif

                @if ($actor->isUser())
                    <a href="{{ route('my.surveys.index') }}" class="hover:underline">アンケート一覧</a>
                @endif
            </nav>

            <div class="ml-auto flex items-center gap-4 text-sm">
                @if ($actor->isSuperuser() && $currentCompany !== null)
                    <span class="rounded bg-slate-600 px-2 py-1" data-testid="selected-company">
                        選択中: {{ $currentCompany->name }}
                    </span>
                    <form method="POST" action="{{ route('admin.reset-company') }}">
                        @csrf
                        <button type="submit" class="hover:underline">全体ビューへ戻る</button>
                    </form>
                @endif

                <span data-testid="current-user">{{ $actor->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded bg-slate-600 px-3 py-1 hover:bg-slate-500">ログアウト</button>
                </form>
            </div>
        @endauth
    </div>
</header>

<main class="mx-auto max-w-6xl px-4 py-6 space-y-4">
    @if (session('success'))
        <div class="rounded border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800" data-testid="flash-success">
            {{ session('success') }}
        </div>
    @endif

    @php
        // 回答画面の設問別エラー（answers.*）は設問直下に表示するため全体ボックスから除外
        $globalErrors = collect($errors->getMessages())
            ->reject(fn ($messages, $key) => str_starts_with($key, 'answers.'))
            ->flatten();
    @endphp
    @if ($globalErrors->isNotEmpty())
        <div class="rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800" data-testid="form-errors">
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($globalErrors as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>
</body>
</html>
