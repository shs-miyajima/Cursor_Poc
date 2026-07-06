<?php

namespace App\Services;

use App\Enums\EquipmentLoanStatus;
use App\Models\Equipment;
use App\Models\EquipmentLoanRequest;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EquipmentLoanApplicationService
{
    /**
     * 部署制限・重複期間・在庫数を検証し、pending で申請を登録する。
     *
     * @param array{requested_from: string, requested_to: string, reason?: string|null} $attributes
     */
    public function create(User $applicant, Equipment $equipment, array $attributes): EquipmentLoanRequest
    {
        $from = Carbon::parse($attributes['requested_from']);
        $to = Carbon::parse($attributes['requested_to']);

        $this->assertDepartmentAllowed($applicant, $equipment);
        $this->assertNoUserOverlap($applicant, $from, $to);
        $this->assertStockAvailable($equipment, $from, $to);

        return EquipmentLoanRequest::create([
            'user_id' => $applicant->id,
            'equipment_id' => $equipment->id,
            'status' => EquipmentLoanStatus::Pending,
            'requested_from' => $from->toDateString(),
            'requested_to' => $to->toDateString(),
            'reason' => $attributes['reason'] ?? null,
        ]);
    }

    /**
     * target_department が NULL、またはユーザー部署と一致することを検証する。
     */
    public function assertDepartmentAllowed(User $user, Equipment $equipment): void
    {
        if (! $equipment->isAvailableForDepartment($user->department)) {
            throw new AccessDeniedHttpException('この備品は所属部署では申請できません');
        }
    }

    /**
     * 同一ユーザーの pending / approved 申請と期間が重複しないことを検証する。
     */
    public function assertNoUserOverlap(User $user, CarbonInterface $from, CarbonInterface $to): void
    {
        $overlapExists = EquipmentLoanRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [EquipmentLoanStatus::Pending, EquipmentLoanStatus::Approved])
            ->whereDate('requested_from', '<=', $to)
            ->whereDate('requested_to', '>=', $from)
            ->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages([
                'requested_from' => '指定期間は既に別の備品を申請中または貸出中です',
            ]);
        }
    }

    /**
     * 同一備品の対象期間における approved 件数が stock_count 未満であることを検証する。
     */
    public function assertStockAvailable(Equipment $equipment, CarbonInterface $from, CarbonInterface $to): void
    {
        $approvedCount = EquipmentLoanRequest::query()
            ->where('equipment_id', $equipment->id)
            ->where('status', EquipmentLoanStatus::Approved)
            ->whereDate('requested_from', '<=', $to)
            ->whereDate('requested_to', '>=', $from)
            ->count();

        if ($approvedCount >= $equipment->stock_count) {
            throw ValidationException::withMessages([
                'equipment_id' => '指定期間は備品の在庫数を超えています',
            ]);
        }
    }
}
