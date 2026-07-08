@extends('layouts.app')

@section('title', 'アンケート編集')

@php $isDraft = $survey->status === \App\Enums\SurveyStatus::Draft; @endphp

@if ($isDraft)
    @push('scripts')
        @vite('resources/js/surveyForm.js')
    @endpush
@endif

@section('content')
<h1 class="text-xl font-bold">アンケート編集</h1>

<form method="POST" action="{{ route('surveys.update', $survey) }}" id="survey-form"
      class="max-w-3xl rounded border border-gray-200 bg-white p-6 space-y-4">
    @csrf
    @method('PUT')

    <div>
        <label for="title" class="mb-1 block text-sm font-medium">タイトル</label>
        <input type="text" id="title" name="title" value="{{ old('title', $survey->title) }}"
               class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
    </div>

    <div>
        <label for="description" class="mb-1 block text-sm font-medium">説明</label>
        <textarea id="description" name="description" rows="3"
                  class="w-full rounded border border-gray-300 px-3 py-2 text-sm">{{ old('description', $survey->description) }}</textarea>
    </div>

    <div>
        <label for="deadline_at" class="mb-1 block text-sm font-medium">締切日時（未指定は無期限）</label>
        <input type="datetime-local" id="deadline_at" name="deadline_at"
               value="{{ old('deadline_at', $survey->deadline_at?->format('Y-m-d\TH:i')) }}"
               class="rounded border border-gray-300 px-3 py-2 text-sm">
    </div>

    @if ($isDraft)
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium">設問</span>
                <button type="button" id="add-question"
                        class="rounded border border-blue-600 px-3 py-1 text-sm text-blue-600 hover:bg-blue-50">
                    設問を追加
                </button>
            </div>
            <div id="question-list" class="space-y-3"></div>
        </div>

        <script type="application/json" id="survey-form-data">{!! json_encode(
            $survey->questions->map(fn ($q) => [
                'body' => $q->body,
                'type' => $q->type->value,
                'is_required' => $q->is_required,
                'options' => $q->options->pluck('label')->all(),
            ])->all(),
            JSON_UNESCAPED_UNICODE
        ) !!}</script>

        <div class="flex gap-3">
            <button type="submit" name="action" value="save"
                    class="rounded border border-gray-400 px-4 py-2 text-sm font-semibold hover:bg-gray-50">
                下書き保存
            </button>
            <button type="submit" name="action" value="publish"
                    class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                公開
            </button>
        </div>
    @else
        {{-- 公開後は設問を変更できない（VAL-27）。読み取り専用で表示する --}}
        <div class="space-y-3">
            <span class="text-sm font-medium">設問（公開後は変更できません）</span>
            @foreach ($survey->questions as $question)
                <div class="rounded border border-gray-200 bg-gray-50 p-4 space-y-2" data-testid="readonly-question-{{ $loop->index }}">
                    <div class="text-sm font-semibold">設問 {{ $loop->iteration }}</div>
                    <input type="text" value="{{ $question->body }}" disabled
                           class="w-full rounded border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-500">
                    <div class="text-xs text-gray-500">
                        形式: {{ $question->type->label() }} / {{ $question->is_required ? '必須' : '任意' }}
                    </div>
                    @foreach ($question->options as $option)
                        <input type="text" value="{{ $option->label }}" disabled
                               class="block w-full max-w-sm rounded border border-gray-200 bg-gray-100 px-3 py-1 text-sm text-gray-500">
                    @endforeach
                </div>
            @endforeach
        </div>

        <div class="flex gap-3">
            <button type="submit"
                    class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                保存
            </button>
        </div>
    @endif

    <a href="{{ route('surveys.index') }}" class="inline-block text-sm text-gray-500 hover:underline">一覧へ戻る</a>
</form>
@endsection
