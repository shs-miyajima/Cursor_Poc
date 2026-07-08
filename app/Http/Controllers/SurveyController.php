<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesCompanyResource;
use App\Http\Requests\StoreSurveyRequest;
use App\Http\Requests\UpdateSurveyRequest;
use App\Models\Company;
use App\Models\Survey;
use App\Services\CompanyContext;
use App\Services\SurveyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SurveyController extends Controller
{
    use AuthorizesCompanyResource;

    public function __construct(
        private readonly CompanyContext $context,
        private readonly SurveyService $service,
    ) {
    }

    public function index(): View
    {
        $company = $this->context->current();

        $surveys = Survey::query()
            ->when($company !== null, fn ($q) => $q->where('company_id', $company->id))
            ->with('company')
            ->withCount('responses')
            ->orderByDesc('created_at')
            ->get();

        return view('surveys.index', ['surveys' => $surveys, 'company' => $company]);
    }

    public function create(): View
    {
        $company = $this->context->current();

        return view('surveys.create', [
            'company' => $company,
            // SU 全体ビューのみ企業選択を表示（UC-10）
            'companies' => $company === null ? Company::orderBy('id')->get() : null,
        ]);
    }

    public function store(StoreSurveyRequest $request): RedirectResponse
    {
        $company = Company::findOrFail($request->targetCompanyId());

        $this->service->create(
            $company,
            $request->user(),
            $request->validated(),
            $request->input('action') === 'publish',
        );

        return redirect()->route('surveys.index')->with('success', 'アンケートを保存しました');
    }

    public function edit(Survey $survey): View
    {
        $this->authorizeCompanyResource($survey->company_id);

        return view('surveys.edit', ['survey' => $survey->load('questions.options')]);
    }

    public function update(UpdateSurveyRequest $request, Survey $survey): RedirectResponse
    {
        $this->authorizeCompanyResource($survey->company_id);

        $survey = $this->service->update($survey, $request->validated());

        // 下書き編集画面の「公開」ボタン（UC-20: 編集して公開）
        if ($request->input('action') === 'publish' && $survey->status === \App\Enums\SurveyStatus::Draft) {
            $this->service->publish($survey);
        }

        return redirect()->route('surveys.index')->with('success', 'アンケートを更新しました');
    }

    public function publish(Survey $survey): RedirectResponse
    {
        $this->authorizeCompanyResource($survey->company_id);

        $this->service->publish($survey);

        return redirect()->route('surveys.index')->with('success', 'アンケートを公開しました');
    }

    public function close(Survey $survey): RedirectResponse
    {
        $this->authorizeCompanyResource($survey->company_id);

        $this->service->close($survey);

        return redirect()->route('surveys.index')->with('success', 'アンケートを終了しました');
    }

    public function destroy(Survey $survey): RedirectResponse
    {
        $this->authorizeCompanyResource($survey->company_id);

        $survey->delete();

        return redirect()->route('surveys.index')->with('success', 'アンケートを削除しました');
    }
}
