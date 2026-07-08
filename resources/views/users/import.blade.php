@extends('layouts.app')

@section('title', 'ユーザー CSV 登録')

@section('content')
<h1 class="text-xl font-bold">ユーザー CSV 登録</h1>

<div class="max-w-2xl rounded border border-gray-200 bg-white p-6 space-y-4">
    <p class="text-sm text-gray-600">
        1 行目はヘッダー行として読み飛ばします。列順:
        氏名, メールアドレス, パスワード, 部署, 性別, 生年月日(YYYY-MM-DD), 入社年月(YYYY-MM)。
        データ行は 500 行以内・ファイルは 2MB 以内です。
    </p>

    <form method="POST" action="{{ route('users.import.upload') }}" enctype="multipart/form-data"
          class="flex items-center gap-4">
        @csrf
        <input type="file" name="file" accept=".csv" data-testid="csv-file"
               class="text-sm file:mr-3 file:rounded file:border-0 file:bg-gray-200 file:px-3 file:py-2">
        <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
            アップロード
        </button>
    </form>
</div>
@endsection
