<?php

namespace App\Services;

use App\Enums\EquipmentLoanStatus;
use App\Models\Equipment;
use App\Models\EquipmentLoanRequest;
use App\Models\User;

class EquipmentLoanQueryService
{
    public function __construct(
        private readonly EquipmentLoanPresenter $presenter,
    ) {
    }

    /**
     * ロールに応じた申請一覧・返却期限超過サマリ・ユーザー/備品候補を組み立てる。
     */
    public function listFor(User $viewer, ?string $search): array
    {
        $canUpdateStatus = $viewer->isAdmin();

        $query = EquipmentLoanRequest::query()
            ->with(['user', 'equipment'])
            ->orderByDesc('id');

        if (! $canUpdateStatus) {
            $query->where('user_id', $viewer->id);
        }

        if ($search !== null && $search !== '') {
            $query->whereHas('equipment', function ($equipmentQuery) use ($search) {
                $equipmentQuery->where('name', 'like', '%' . $search . '%');
            });
        }

        $loans = $query->get();

        // アラートは検索条件に依存させず、閲覧ユーザーの範囲全体から算出する
        $overdueQuery = EquipmentLoanRequest::query()->with(['user', 'equipment']);
        if (! $canUpdateStatus) {
            $overdueQuery->where('user_id', $viewer->id);
        }
        $overdueLoans = $overdueQuery->get()
            ->filter(fn (EquipmentLoanRequest $loan) => $loan->isOverdue())
            ->values();

        return [
            'viewer' => [
                'id' => $viewer->id,
                'name' => $viewer->name,
                'department' => $viewer->department,
                'role' => $viewer->role->value,
            ],
            'items' => $loans
                ->map(fn (EquipmentLoanRequest $loan) => $this->presenter->toItemArray(
                    $loan,
                    $canUpdateStatus,
                    $this->canRequestReturn($viewer, $loan),
                ))
                ->values()
                ->all(),
            'overdue_summary' => $this->presenter->toOverdueSummary($overdueLoans),
            'users' => User::query()
                ->orderBy('id')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'department' => $user->department,
                    'role' => $user->role->value,
                ])
                ->all(),
            'equipments' => Equipment::query()
                ->orderBy('id')
                ->get()
                ->map(fn (Equipment $equipment) => [
                    'id' => $equipment->id,
                    'name' => $equipment->name,
                    'stock_count' => $equipment->stock_count,
                    'target_department' => $equipment->target_department,
                ])
                ->all(),
        ];
    }

    /**
     * 申請者本人が閲覧している貸出中（approved）の申請かどうかを判定する。
     */
    private function canRequestReturn(User $viewer, EquipmentLoanRequest $loan): bool
    {
        return $loan->user_id === $viewer->id && $loan->status === EquipmentLoanStatus::Approved;
    }
}
