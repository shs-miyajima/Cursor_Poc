@extends('layouts.app')

@section('title', 'ダッシュボード')

@push('scripts')
    @vite('resources/js/dashboard.js')
@endpush

@section('content')
<h1 class="text-xl font-bold">ダッシュボード</h1>

@if ($surveys->isEmpty())
    <p class="text-sm text-gray-600">公開中のアンケートがありません。</p>
@else
    <div id="dashboard" class="space-y-4">
        <div class="rounded border border-gray-200 bg-white p-4 space-y-4">
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label for="survey-select" class="mb-1 block text-sm font-medium">アンケート</label>
                    <select id="survey-select" class="w-72 rounded border border-gray-300 px-3 py-2 text-sm">
                        @foreach ($surveys as $survey)
                            <option value="{{ $survey->id }}">{{ $survey->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="chart-type-select" class="mb-1 block text-sm font-medium">グラフ形式</label>
                    <select id="chart-type-select" class="rounded border border-gray-300 px-3 py-2 text-sm">
                        <option value="bar">縦棒グラフ</option>
                        <option value="pie">円グラフ</option>
                    </select>
                </div>
                <div class="ml-auto text-sm">
                    回答件数: <span id="total-responses" data-testid="total-responses" class="font-bold"></span>
                </div>
            </div>

            <form id="filter-form" class="flex flex-wrap items-end gap-3 border-t border-gray-100 pt-4 text-sm">
                <div>
                    <label for="filter-department" class="mb-1 block text-xs text-gray-500">部署</label>
                    <select id="filter-department" name="department_id" class="rounded border border-gray-300 px-2 py-1">
                        <option value="">指定なし</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filter-date-from" class="mb-1 block text-xs text-gray-500">回答日時（から）</label>
                    <input type="date" id="filter-date-from" name="date_from" class="rounded border border-gray-300 px-2 py-1">
                </div>
                <div>
                    <label for="filter-date-to" class="mb-1 block text-xs text-gray-500">回答日時（まで）</label>
                    <input type="date" id="filter-date-to" name="date_to" class="rounded border border-gray-300 px-2 py-1">
                </div>
                <div>
                    <label for="filter-gender" class="mb-1 block text-xs text-gray-500">性別</label>
                    <select id="filter-gender" name="gender" class="rounded border border-gray-300 px-2 py-1">
                        <option value="">指定なし</option>
                        @foreach ($genders as $gender)
                            <option value="{{ $gender->value }}">{{ $gender->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filter-age-group" class="mb-1 block text-xs text-gray-500">年代</label>
                    <select id="filter-age-group" name="age_group" class="rounded border border-gray-300 px-2 py-1">
                        <option value="">指定なし</option>
                        @foreach ($ageGroups as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filter-hired-from" class="mb-1 block text-xs text-gray-500">入社年月（から）</label>
                    <input type="month" id="filter-hired-from" name="hired_from" class="rounded border border-gray-300 px-2 py-1">
                </div>
                <div>
                    <label for="filter-hired-to" class="mb-1 block text-xs text-gray-500">入社年月（まで）</label>
                    <input type="month" id="filter-hired-to" name="hired_to" class="rounded border border-gray-300 px-2 py-1">
                </div>
                <button type="submit" class="rounded bg-blue-600 px-4 py-1.5 font-semibold text-white hover:bg-blue-500">
                    絞り込み
                </button>
            </form>
        </div>

        <div id="question-results" class="space-y-4"></div>
    </div>
@endif
@endsection
