<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesCompanyResource;
use App\Models\Survey;
use App\Services\SurveyResultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SurveyResultController extends Controller
{
    use AuthorizesCompanyResource;

    /**
     * GET /api/surveys/{survey}/results — ダッシュボード用集計 JSON（設計 §3.3）。
     */
    public function show(Request $request, Survey $survey, SurveyResultService $service): JsonResponse
    {
        $this->authorizeCompanyResource($survey->company_id);

        return response()->json(
            $service->aggregate($survey, $service->buildFilter($request->query())),
        );
    }
}
