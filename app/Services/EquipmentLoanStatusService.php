<?php

namespace App\Services;

use App\Enums\EquipmentLoanStatus;
use App\Models\EquipmentLoanRequest;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EquipmentLoanStatusService
{
    /**
     * 管理者のみステータスを approved / rejected / returned に更新する。
     * ステータス遷移順序の制約は設けない。
     */
    public function update(User $operator, EquipmentLoanRequest $loan, EquipmentLoanStatus $status): EquipmentLoanRequest
    {
        if (! $operator->isAdmin()) {
            throw new AccessDeniedHttpException('ステータスを更新する権限がありません');
        }

        $loan->update(['status' => $status]);

        return $loan->refresh();
    }
}
