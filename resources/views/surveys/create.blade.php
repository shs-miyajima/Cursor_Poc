@extends('layouts.app')

@section('title', 'アンケート作成')

@push('scripts')
    @vite('resources/js/surveyForm.js')
@endpush

@section('content')
<h1 class="text-xl font-bold">アンケート作成</h1>

<form method="POST" action="{{ route('surveys.store') }}" id="survey-form"
      class="max-w-3xl rounded border border-gray-200 bg-white p-6 space-y-4">
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
        <label for="title" class="mb-1 block text-sm font-medium">タイトル</label>
        <input type="text" id="title" name="title" value="{{ old('title') }}"
               class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
    </div>

    <div>
        <label for="description" class="mb-1 block text-sm font-medium">説明</label>
        <textarea id="description" name="description" rows="3"
                  class="w-full rounded border border-gray-300 px-3 py-2 text-sm">{{ old('description') }}</textarea>
    </div>

    <div>
        <label for="deadline_at" class="mb-1 block text-sm font-medium">締切日時（未指定は無期限）</label>
        <input type="datetime-local" id="deadline_at" name="deadline_at" value="{{ old('deadline_at') }}"
               class="rounded border border-gray-300 px-3 py-2 text-sm">
    </div>

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

    <div class="flex gap-3">
        <button type="submit" name="action" value="draft"
                class="rounded border border-gray-400 px-4 py-2 text-sm font-semibold hover:bg-gray-50">
            下書き保存
        </button>
        <button type="submit" name="action" value="publish"
                class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
            公開
        </button>
    </div>
</form>
@endsection
