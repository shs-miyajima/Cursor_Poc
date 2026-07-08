@extends('layouts.app')

@section('title', '全体ビュー')

@section('content')
<h1 class="text-xl font-bold">全体ビュー</h1>

<div class="grid grid-cols-3 gap-4">
    <div class="rounded border border-gray-200 bg-white p-4 text-center">
        <div class="text-sm text-gray-500">企業数</div>
        <div class="text-2xl font-bold" data-testid="summary-companies">{{ $companyCount }}</div>
    </div>
    <div class="rounded border border-gray-200 bg-white p-4 text-center">
        <div class="text-sm text-gray-500">アンケート数</div>
        <div class="text-2xl font-bold" data-testid="summary-surveys">{{ $surveyCount }}</div>
    </div>
    <div class="rounded border border-gray-200 bg-white p-4 text-center">
        <div class="text-sm text-gray-500">回答数</div>
        <div class="text-2xl font-bold" data-testid="summary-responses">{{ $responseCount }}</div>
    </div>
</div>

<div class="rounded border border-gray-200 bg-white p-4">
    <h2 class="mb-3 font-semibold">企業一覧</h2>
    <table class="w-full text-left text-sm">
        <thead>
        <tr class="border-b text-gray-500">
            <th class="py-2">企業名</th>
            <th class="py-2">企業コード</th>
            <th class="py-2"></th>
        </tr>
        </thead>
        <tbody>
        @foreach ($companies as $company)
            <tr class="border-b" data-testid="company-row-{{ $company->id }}">
                <td class="py-2">{{ $company->name }}</td>
                <td class="py-2">{{ $company->code }}</td>
                <td class="py-2 text-right">
                    <form method="POST" action="{{ route('admin.switch-company', $company) }}" class="inline">
                        @csrf
                        <button type="submit" class="rounded bg-blue-600 px-3 py-1 text-white hover:bg-blue-500">
                            企業ビューへ切替
                        </button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
