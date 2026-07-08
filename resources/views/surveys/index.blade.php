@extends('layouts.app')

@section('title', 'アンケート管理')

@section('content')
<div class="flex items-center justify-between">
    <h1 class="text-xl font-bold">アンケート管理</h1>
    <a href="{{ route('surveys.create') }}"
       class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
        新規作成
    </a>
</div>

<div class="rounded border border-gray-200 bg-white p-4">
    <table class="w-full text-left text-sm">
        <thead>
        <tr class="border-b text-gray-500">
            <th class="py-2">タイトル</th>
            @if ($company === null)
                <th class="py-2">企業</th>
            @endif
            <th class="py-2">状態</th>
            <th class="py-2">締切日時</th>
            <th class="py-2">回答数</th>
            <th class="py-2">作成日</th>
            <th class="py-2"></th>
        </tr>
        </thead>
        <tbody>
        @foreach ($surveys as $survey)
            @php $status = $survey->effectiveStatus(); @endphp
            <tr class="border-b" data-testid="survey-row-{{ $survey->id }}">
                <td class="py-2">{{ $survey->title }}</td>
                @if ($company === null)
                    <td class="py-2">{{ $survey->company?->name }}</td>
                @endif
                <td class="py-2" data-testid="survey-status-{{ $survey->id }}">{{ $status->label() }}</td>
                <td class="py-2">{{ $survey->deadline_at?->format('Y-m-d H:i') }}</td>
                <td class="py-2">{{ $survey->responses_count }}</td>
                <td class="py-2">{{ $survey->created_at->format('Y-m-d') }}</td>
                <td class="py-2 text-right space-x-2 whitespace-nowrap">
                    @if ($survey->status === \App\Enums\SurveyStatus::Draft)
                        <form method="POST" action="{{ route('surveys.publish', $survey) }}" class="inline">
                            @csrf
                            <button type="submit" class="rounded bg-green-600 px-3 py-1 text-white hover:bg-green-500">公開</button>
                        </form>
                    @elseif ($survey->status === \App\Enums\SurveyStatus::Published)
                        <form method="POST" action="{{ route('surveys.close', $survey) }}" class="inline">
                            @csrf
                            <button type="submit" class="rounded bg-amber-600 px-3 py-1 text-white hover:bg-amber-500">終了</button>
                        </form>
                    @endif
                    <a href="{{ route('surveys.edit', $survey) }}" class="text-blue-600 hover:underline">編集</a>
                    <form method="POST" action="{{ route('surveys.destroy', $survey) }}" class="inline"
                          data-confirm="アンケート「{{ $survey->title }}」を削除しますか？">
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
