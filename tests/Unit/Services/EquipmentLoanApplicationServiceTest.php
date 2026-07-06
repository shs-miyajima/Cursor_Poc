<?php

namespace Tests\Unit\Services;

use App\Enums\EquipmentLoanStatus;
use App\Models\Equipment;
use App\Models\EquipmentLoanRequest;
use App\Models\User;
use App\Services\EquipmentLoanApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class EquipmentLoanApplicationServiceTest extends TestCase
{
    use RefreshDatabase;

    private EquipmentLoanApplicationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EquipmentLoanApplicationService();
    }

    private function makeStaff(string $department = '開発部'): User
    {
        return User::factory()->create(['department' => $department, 'role' => 'staff']);
    }

    /**
     * PHPUnit-inp-001: 理由 任意入力なし
     */
    public function test_理由が空でも申請が作成される(): void
    {
        $user = $this->makeStaff();
        $equipment = Equipment::factory()->create();

        $loan = $this->service->create($user, $equipment, [
            'requested_from' => Carbon::today()->addDays(7)->toDateString(),
            'requested_to' => Carbon::today()->addDays(9)->toDateString(),
            'reason' => null,
        ]);

        $this->assertSame(EquipmentLoanStatus::Pending, $loan->status);
        $this->assertNull($loan->reason);
    }

    /**
     * PHPUnit-inp-002: 貸出同日申請
     */
    public function test_開始日と終了日が同日の申請が作成される(): void
    {
        $user = $this->makeStaff();
        $equipment = Equipment::factory()->create();
        $sameDay = Carbon::today()->addDays(7)->toDateString();

        $loan = $this->service->create($user, $equipment, [
            'requested_from' => $sameDay,
            'requested_to' => $sameDay,
        ]);

        $this->assertSame($sameDay, $loan->requested_from->toDateString());
        $this->assertSame($sameDay, $loan->requested_to->toDateString());
    }

    /**
     * PHPUnit-inp-003: 通常期間申請 OK
     */
    public function test_通常期間の申請がpendingで作成され各項目が保存される(): void
    {
        $user = $this->makeStaff();
        $equipment = Equipment::factory()->create();
        $from = Carbon::today()->addDays(7)->toDateString();
        $to = Carbon::today()->addDays(10)->toDateString();

        $loan = $this->service->create($user, $equipment, [
            'requested_from' => $from,
            'requested_to' => $to,
            'reason' => '検証作業で使用するため',
        ]);

        $this->assertSame(EquipmentLoanStatus::Pending, $loan->status);
        $this->assertSame($from, $loan->requested_from->toDateString());
        $this->assertSame($to, $loan->requested_to->toDateString());
        $this->assertSame('検証作業で使用するため', $loan->reason);
        $this->assertDatabaseHas('equipment_loan_requests', [
            'id' => $loan->id,
            'user_id' => $user->id,
            'equipment_id' => $equipment->id,
            'status' => 'pending',
        ]);
    }

    /**
     * PHPUnit-dyn-001: 部署制限 OK
     */
    public function test_部署が一致する備品は例外にならない(): void
    {
        $user = $this->makeStaff('開発部');
        $equipment = Equipment::factory()->create(['target_department' => '開発部']);

        $this->service->assertDepartmentAllowed($user, $equipment);

        $this->assertTrue(true);
    }

    /**
     * PHPUnit-dyn-002: 部署制限 NG
     */
    public function test_部署が一致しない備品は403相当の例外になる(): void
    {
        $user = $this->makeStaff('総務部');
        $equipment = Equipment::factory()->create(['target_department' => '開発部']);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('この備品は所属部署では申請できません');

        $this->service->assertDepartmentAllowed($user, $equipment);
    }

    /**
     * PHPUnit-dyn-011: 部署制限なし備品 OK
     */
    public function test_対象部署がNULLの備品は部署に関係なく例外にならない(): void
    {
        $user = $this->makeStaff('総務部');
        $equipment = Equipment::factory()->create(['target_department' => null]);

        $this->service->assertDepartmentAllowed($user, $equipment);

        $this->assertTrue(true);
    }

    /**
     * PHPUnit-dyn-003: 同一ユーザー重複期間 NG
     */
    public function test_同一ユーザーの重複期間の申請は422相当の例外になる(): void
    {
        $user = $this->makeStaff();
        EquipmentLoanRequest::factory()->create([
            'user_id' => $user->id,
            'status' => EquipmentLoanStatus::Approved,
            'requested_from' => Carbon::today()->addDays(7)->toDateString(),
            'requested_to' => Carbon::today()->addDays(9)->toDateString(),
        ]);

        try {
            $this->service->assertNoUserOverlap(
                $user,
                Carbon::today()->addDays(8),
                Carbon::today()->addDays(12),
            );
            $this->fail('ValidationException が発生していません');
        } catch (ValidationException $e) {
            $this->assertSame(
                '指定期間は既に別の備品を申請中または貸出中です',
                $e->validator->errors()->first(),
            );
        }
    }

    /**
     * PHPUnit-dyn-004: returned rejected は重複対象外
     */
    public function test_同期間にreturnedとrejectedの申請のみの場合は例外にならない(): void
    {
        $user = $this->makeStaff();
        $from = Carbon::today()->addDays(7);
        $to = Carbon::today()->addDays(9);
        EquipmentLoanRequest::factory()->create([
            'user_id' => $user->id,
            'status' => EquipmentLoanStatus::Returned,
            'requested_from' => $from->toDateString(),
            'requested_to' => $to->toDateString(),
        ]);
        EquipmentLoanRequest::factory()->create([
            'user_id' => $user->id,
            'status' => EquipmentLoanStatus::Rejected,
            'requested_from' => $from->toDateString(),
            'requested_to' => $to->toDateString(),
        ]);

        $this->service->assertNoUserOverlap($user, $from, $to);

        $this->assertTrue(true);
    }

    /**
     * PHPUnit-dyn-005: 在庫数上限 NG
     */
    public function test_同期間のapproved件数が在庫数以上の場合は422相当の例外になる(): void
    {
        $equipment = Equipment::factory()->create(['stock_count' => 1]);
        EquipmentLoanRequest::factory()->create([
            'equipment_id' => $equipment->id,
            'status' => EquipmentLoanStatus::Approved,
            'requested_from' => Carbon::today()->addDays(7)->toDateString(),
            'requested_to' => Carbon::today()->addDays(9)->toDateString(),
        ]);

        try {
            $this->service->assertStockAvailable(
                $equipment,
                Carbon::today()->addDays(8),
                Carbon::today()->addDays(8),
            );
            $this->fail('ValidationException が発生していません');
        } catch (ValidationException $e) {
            $this->assertSame(
                '指定期間は備品の在庫数を超えています',
                $e->validator->errors()->first(),
            );
        }
    }

    /**
     * PHPUnit-dyn-006: 在庫数上限未満 OK
     */
    public function test_同期間のapproved件数が在庫数未満の場合は例外にならない(): void
    {
        $equipment = Equipment::factory()->create(['stock_count' => 2]);
        EquipmentLoanRequest::factory()->create([
            'equipment_id' => $equipment->id,
            'status' => EquipmentLoanStatus::Approved,
            'requested_from' => Carbon::today()->addDays(7)->toDateString(),
            'requested_to' => Carbon::today()->addDays(9)->toDateString(),
        ]);

        $this->service->assertStockAvailable(
            $equipment,
            Carbon::today()->addDays(8),
            Carbon::today()->addDays(8),
        );

        $this->assertTrue(true);
    }
}
