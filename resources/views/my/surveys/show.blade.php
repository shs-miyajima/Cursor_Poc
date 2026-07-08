@extends('layouts.app')

@section('title', $survey->title)

@section('content')
<h1 class="text-xl font-bold">{{ $survey->title }}</h1>

@if ($survey->description !== null)
    <p class="text-sm text-gray-600">{{ $survey->description }}</p>
@endif

@if (! $accepting && ! $hasResponse)
    <div class="rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800" data-testid="closed-message">
        回答受付は終了しました
    </div>
@else
    <form method="POST" action="{{ route('my.surveys.answer', $survey) }}"
          class="max-w-2xl rounded border border-gray-200 bg-white p-6 space-y-6">
        @csrf

        @foreach ($survey->questions as $question)
            <div class="space-y-2" data-testid="question-{{ $question->id }}">
                <div class="text-sm font-semibold">
                    設問 {{ $loop->iteration }}. {{ $question->body }}
                    @if ($question->is_required)
                        <span class="text-red-600">*</span>
                    @endif
                </div>

                @if ($question->type === \App\Enums\QuestionType::Single)
                    @foreach ($question->options as $option)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" name="answers[{{ $question->id }}]" value="{{ $option->id }}"
                                   @checked(old("answers.{$question->id}", $previous[$question->id] ?? null) == $option->id)
                                   @disabled(! $accepting)>
                            {{ $option->label }}
                        </label>
                    @endforeach
                @elseif ($question->type === \App\Enums\QuestionType::Multiple)
                    @foreach ($question->options as $option)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="answers[{{ $question->id }}][]" value="{{ $option->id }}"
                                   @checked(in_array($option->id, old("answers.{$question->id}", $previous[$question->id] ?? []) ?: []))
                                   @disabled(! $accepting)>
                            {{ $option->label }}
                        </label>
                    @endforeach
                @else
                    <textarea name="answers[{{ $question->id }}]" rows="3"
                              class="w-full rounded border border-gray-300 px-3 py-2 text-sm disabled:bg-gray-100"
                              @disabled(! $accepting)>{{ old("answers.{$question->id}", $previous[$question->id] ?? '') }}</textarea>
                @endif

                @if ($errors->has("answers.{$question->id}"))
                    <p class="text-sm text-red-600" data-testid="question-error-{{ $question->id }}">
                        {{ $errors->first("answers.{$question->id}") }}
                    </p>
                @endif
            </div>
        @endforeach

        @if ($accepting)
            <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                {{ $hasResponse ? '回答を修正する' : '回答を送信する' }}
            </button>
        @endif
    </form>
@endif

<a href="{{ route('my.surveys.index') }}" class="inline-block text-sm text-gray-500 hover:underline">一覧へ戻る</a>
@endsection
