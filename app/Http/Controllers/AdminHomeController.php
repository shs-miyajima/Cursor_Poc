<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Services\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminHomeController extends Controller
{
    /**
     * 全体ビュー（S-03）: 全企業サマリと企業一覧（UC-09）。
     */
    public function home(): View
    {
        return view('admin.home', [
            'companyCount' => Company::count(),
            'surveyCount' => Survey::count(),
            'responseCount' => SurveyResponse::count(),
            'companies' => Company::orderBy('id')->get(),
        ]);
    }

    public function switchCompany(Company $company, CompanyContext $context): RedirectResponse
    {
        $context->switchTo($company);

        return redirect()->route('dashboard');
    }

    public function resetCompany(CompanyContext $context): RedirectResponse
    {
        $context->switchTo(null);

        return redirect()->route('admin.home');
    }
}
