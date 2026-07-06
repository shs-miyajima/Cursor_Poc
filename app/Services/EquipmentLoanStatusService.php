<?php

namespace App\Services;

use App\Enums\EquipmentLoanStatus;
use App\Models\EquipmentLoanRequest;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EquipmentLoanStatusService
{
    /**
     * 管理者は approved / rejected / returned に更新できる（遷移順序の制約なし）。
     * 申請者本人は、自分の approved の申請に限り return_requested（返却申請）を指定できる。
     */
    public function update(User $operator, EquipmentLoanRequest $loan, EquipmentLoanStatus $status): EquipmentLoanRequest
    {
        if ($status === EquipmentLoanStatus::ReturnRequested) {
            if ($loan->user_id !== $operator->id) {
                throw new AccessDeniedHttpException('自分の申請のみ返却申請できます');
            }

            if ($loan->status !== EquipmentLoanStatus::Approved) {
                throw ValidationException::withMessages([
                    'status' => '現在のステータスでは返却申請できません',
                ]);
            }
        } elseif (! $operator->isAdmin()) {
            throw new AccessDeniedHttpException('ステータスを更新する権限がありません');
        }

        $loan->update(['status' => $status]);

        return $loan->refresh();
    }
}
