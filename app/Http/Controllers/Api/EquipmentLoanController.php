<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListEquipmentLoansRequest;
use App\Http\Requests\StoreEquipmentLoanRequest;
use App\Models\Equipment;
use App\Services\EquipmentLoanApplicationService;
use App\Services\EquipmentLoanPresenter;
use App\Services\EquipmentLoanQueryService;
use App\Services\MockUserResolver;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EquipmentLoanController extends Controller
{
    public function __construct(
        private readonly MockUserResolver $mockUserResolver,
        private readonly EquipmentLoanQueryService $queryService,
        private readonly EquipmentLoanApplicationService $applicationService,
        private readonly EquipmentLoanPresenter $presenter,
    ) {
    }

    public function index(ListEquipmentLoansRequest $request): JsonResponse
    {
        $viewer = $this->mockUserResolver->resolve((int) $request->validated('mock_user_id'));

        return response()->json(
            $this->queryService->listFor($viewer, $request->validated('search'))
        );
    }

    public function store(StoreEquipmentLoanRequest $request): JsonResponse
    {
        $applicant = $this->mockUserResolver->resolve((int) $request->validated('mock_user_id'));

        $equipment = Equipment::find((int) $request->validated('equipment_id'));
        if ($equipment === null) {
            throw new NotFoundHttpException('備品が見つかりません');
        }

        $loan = $this->applicationService->create($applicant, $equipment, [
            'requested_from' => $request->validated('requested_from'),
            'requested_to' => $request->validated('requested_to'),
            'reason' => $request->validated('reason'),
        ]);

        $loan->load(['user', 'equipment']);

        return response()->json([
            'item' => $this->presenter->toItemArray($loan, $applicant->isAdmin()),
        ], 201);
    }
}
