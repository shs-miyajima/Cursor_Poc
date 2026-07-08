<?php

namespace App\Http\Controllers;

use App\Enums\Gender;
use App\Enums\SurveyStatus;
use App\Services\AgeGroupResolver;
use App\Services\CompanyContext;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly CompanyContext $context)
    {
    }

    /**
     * S-08: ダッシュボード。下書きは選択肢に含めない。初期選択は最新の公開アンケート。
     */
    public function index(): View
    {
        $company = $this->context->requireCompany();

        $surveys = $company->surveys()
            ->whereNot('status', SurveyStatus::Draft)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return view('dashboard.index', [
            'surveys' => $surveys,
            'departments' => $company->departments()->orderBy('id')->get(),
            'genders' => Gender::cases(),
            'ageGroups' => AgeGroupResolver::GROUPS,
        ]);
    }
}
