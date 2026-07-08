@extends('layouts.app')

@section('title', 'アンケート一覧')

@section('content')
<h1 class="text-xl font-bold">アンケート一覧</h1>

<div class="rounded border border-gray-200 bg-white p-4">
    <h2 class="mb-3 font-semibold">未回答のアンケート</h2>
    @if ($unanswered->isEmpty())
        <p class="text-sm text-gray-500">未回答のアンケートはありません。</p>
    @else
        <ul class="space-y-2 text-sm" data-testid="unanswered-list">
            @foreach ($unanswered as $survey)
                <li class="flex items-center gap-4 border-b pb-2" data-testid="survey-item-{{ $survey->id }}">
                    <a href="{{ route('my.surveys.show', $survey) }}" class="text-blue-600 hover:underline">
                        {{ $survey->title }}
                    </a>
                    <span class="text-gray-500">{{ $survey->effectiveStatus()->label() }}</span>
                    @if ($survey->deadline_at !== null)
                        <span class="text-gray-500">締切: {{ $survey->deadline_at->format('Y-m-d H:i') }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>

<div class="rounded border border-gray-200 bg-white p-4">
    <h2 class="mb-3 font-semibold">回答済のアンケート</h2>
    @if ($answered->isEmpty())
        <p class="text-sm text-gray-500">回答済のアンケートはありません。</p>
    @else
        <ul class="space-y-2 text-sm" data-testid="answered-list">
            @foreach ($answered as $survey)
                <li class="flex items-center gap-4 border-b pb-2" data-testid="survey-item-{{ $survey->id }}">
                    <a href="{{ route('my.surveys.show', $survey) }}" class="text-blue-600 hover:underline">
                        {{ $survey->title }}
                    </a>
                    <span class="text-gray-500">{{ $survey->effectiveStatus()->label() }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
