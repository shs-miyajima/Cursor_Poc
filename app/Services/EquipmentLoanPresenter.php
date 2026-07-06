<?php

namespace App\Services;

use App\Models\EquipmentLoanRequest;
use Illuminate\Support\Collection;

class EquipmentLoanPresenter
{
    /**
     * 一覧行用 JSON を作成する。
     */
    public function toItemArray(EquipmentLoanRequest $loan, bool $canUpdateStatus = false): array
    {
        return [
            'id' => $loan->id,
            'user_name' => $loan->user->name,
            'equipment_name' => $loan->equipment->name,
            'status' => $loan->status->value,
            'status_label' => $loan->status->label(),
            'requested_from' => $loan->requested_from->toDateString(),
            'requested_to' => $loan->requested_to->toDateString(),
            'reason' => $loan->reason,
            'is_overdue' => $loan->isOverdue(),
            'can_update_status' => $canUpdateStatus,
        ];
    }

    /**
     * 返却期限超過件数とアラート表示用の対象概要を作成する。
     *
     * @param Collection<int, EquipmentLoanRequest> $loans 返却期限超過の申請のみを渡す
     */
    public function toOverdueSummary(Collection $loans): array
    {
        return [
            'count' => $loans->count(),
            'items' => $loans->map(fn (EquipmentLoanRequest $loan) => [
                'id' => $loan->id,
                'user_name' => $loan->user->name,
                'equipment_name' => $loan->equipment->name,
                'requested_to' => $loan->requested_to->toDateString(),
            ])->values()->all(),
        ];
    }
}
