<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>備品貸出管理 - Cursor_Poc</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 min-h-screen">
    <div id="equipment-loan-app" class="max-w-6xl mx-auto px-4 py-8">
        <header class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">備品貸出管理</h1>
        </header>

        {{-- 擬似ログイン: 現在のユーザー --}}
        <section class="mb-6 bg-white rounded-lg shadow p-4">
            <label for="mock-user-select" class="block text-sm font-medium text-gray-700 mb-1">現在のユーザー</label>
            <select id="mock-user-select" data-testid="mock-user-select"
                    class="w-full max-w-sm rounded border-gray-300 border px-3 py-2 bg-white">
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}（{{ $user->department }} / {{ $user->role->value === 'admin' ? '管理者' : '一般社員' }}）</option>
                @endforeach
            </select>
        </section>

        {{-- 返却期限超過アラート --}}
        <section id="overdue-alert" data-testid="overdue-alert" hidden
                 class="mb-6 rounded-lg border border-red-300 bg-red-50 p-4 text-red-800">
        </section>

        {{-- API エラー表示 --}}
        <div id="error-message" data-testid="error-message" hidden
             class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-800">
        </div>

        {{-- 検索 --}}
        <section class="mb-6 bg-white rounded-lg shadow p-4">
            <label for="loan-search-input" class="block text-sm font-medium text-gray-700 mb-1">備品名検索</label>
            <div class="flex gap-2">
                <input type="text" id="loan-search-input" data-testid="loan-search-input" placeholder="備品名（部分一致）"
                       class="w-full max-w-sm rounded border-gray-300 border px-3 py-2">
                <button type="button" id="loan-search-button" data-testid="loan-search-button"
                        class="rounded bg-gray-700 px-4 py-2 text-white hover:bg-gray-600">検索</button>
            </div>
        </section>

        {{-- 申請一覧 --}}
        <section class="mb-6 bg-white rounded-lg shadow p-4">
            <h2 class="text-lg font-semibold text-gray-800 mb-3">申請一覧</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-gray-600">
                        <tr>
                            <th class="px-3 py-2"></th>
                            <th class="px-3 py-2">申請者</th>
                            <th class="px-3 py-2">備品名</th>
                            <th class="px-3 py-2">ステータス</th>
                            <th class="px-3 py-2">貸出開始日</th>
                            <th class="px-3 py-2">貸出終了日</th>
                            <th class="px-3 py-2">理由</th>
                            <th class="px-3 py-2">操作</th>
                        </tr>
                    </thead>
                    <tbody id="loan-table-body" data-testid="loan-table-body">
                    </tbody>
                </table>
            </div>
        </section>

        {{-- 新規申請 --}}
        <section class="bg-white rounded-lg shadow p-4">
            <h2 class="text-lg font-semibold text-gray-800 mb-3">新規申請</h2>
            <form id="new-loan-form" data-testid="new-loan-form" class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="loan-equipment-select" class="block text-sm font-medium text-gray-700 mb-1">備品 <span class="text-red-600">*</span></label>
                    <select id="loan-equipment-select" name="equipment_id" data-testid="loan-equipment-select"
                            class="w-full rounded border-gray-300 border px-3 py-2 bg-white">
                        <option value="">選択してください</option>
                        @foreach ($equipments as $equipment)
                            <option value="{{ $equipment->id }}">{{ $equipment->name }}（対象: {{ $equipment->target_department ?? '全部署' }}）</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="loan-reason-input" class="block text-sm font-medium text-gray-700 mb-1">理由（任意）</label>
                    <textarea id="loan-reason-input" name="reason" data-testid="loan-reason-input" rows="1"
                              class="w-full rounded border-gray-300 border px-3 py-2"></textarea>
                </div>
                <div>
                    <label for="loan-from-input" class="block text-sm font-medium text-gray-700 mb-1">貸出開始日 <span class="text-red-600">*</span></label>
                    <input type="date" id="loan-from-input" name="requested_from" data-testid="loan-from-input"
                           class="w-full rounded border-gray-300 border px-3 py-2">
                </div>
                <div>
                    <label for="loan-to-input" class="block text-sm font-medium text-gray-700 mb-1">貸出終了日 <span class="text-red-600">*</span></label>
                    <input type="date" id="loan-to-input" name="requested_to" data-testid="loan-to-input"
                           class="w-full rounded border-gray-300 border px-3 py-2">
                </div>
                <div class="md:col-span-2">
                    <button type="submit" data-testid="loan-submit-button"
                            class="rounded bg-blue-600 px-6 py-2 text-white hover:bg-blue-500">申請する</button>
                </div>
            </form>
        </section>
    </div>
</body>
</html>
