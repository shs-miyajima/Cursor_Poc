<?php

namespace App\Http\Controllers\Api;

use App\Enums\EquipmentLoanStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateEquipmentLoanStatusRequest;
use App\Models\EquipmentLoanRequest;
use App\Services\EquipmentLoanPresenter;
use App\Services\EquipmentLoanStatusService;
use App\Services\MockUserResolver;
use Illuminate\Http\JsonResponse;

class EquipmentLoanStatusController extends Controller
{
    public function __construct(
        private readonly MockUserResolver $mockUserResolver,
        private readonly EquipmentLoanStatusService $statusService,
        private readonly EquipmentLoanPresenter $presenter,
    ) {
    }

    public function update(
        UpdateEquipmentLoanStatusRequest $request,
        EquipmentLoanRequest $equipmentLoan,
    ): JsonResponse {
        $operator = $this->mockUserResolver->resolve((int) $request->validated('mock_user_id'));

        $loan = $this->statusService->update(
            $operator,
            $equipmentLoan,
            EquipmentLoanStatus::from($request->validated('status')),
        );

        $loan->load(['user', 'equipment']);

        $canRequestReturn = $loan->user_id === $operator->id && $loan->status === EquipmentLoanStatus::Approved;

        return response()->json([
            'item' => $this->presenter->toItemArray($loan, $operator->isAdmin(), $canRequestReturn),
        ]);
    }
}
