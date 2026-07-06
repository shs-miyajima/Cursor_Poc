<?php

namespace Tests\Unit\Services;

use App\Enums\EquipmentLoanStatus;
use App\Models\EquipmentLoanRequest;
use App\Models\User;
use App\Services\EquipmentLoanPresenter;
use App\Services\EquipmentLoanQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentLoanQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private EquipmentLoanQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EquipmentLoanQueryService(new EquipmentLoanPresenter());
    }

    /**
     * PHPUnit-dyn-009: 一般社員一覧範囲 OK
     */
    public function test_一般社員には自分の申請だけが返る(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $other = User::factory()->create(['role' => 'staff']);
        $ownLoan = EquipmentLoanRequest::factory()->create(['user_id' => $staff->id]);
        EquipmentLoanRequest::factory()->create(['user_id' => $other->id]);

        $result = $this->service->listFor($staff, null);

        $this->assertCount(1, $result['items']);
        $this->assertSame($ownLoan->id, $result['items'][0]['id']);
    }

    /**
     * PHPUnit-dyn-010: 管理者一覧範囲 OK
     */
    public function test_管理者には全ユーザーの申請が返る(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff1 = User::factory()->create(['role' => 'staff']);
        $staff2 = User::factory()->create(['role' => 'staff']);
        EquipmentLoanRequest::factory()->create(['user_id' => $staff1->id]);
        EquipmentLoanRequest::factory()->create(['user_id' => $staff2->id]);

        $result = $this->service->listFor($admin, null);

        $this->assertCount(2, $result['items']);
    }

    /**
     * PHPUnit-other-001: 一覧レスポンス形式
     */
    public function test_一覧レスポンスに必要なキーが含まれる(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        EquipmentLoanRequest::factory()->create([
            'user_id' => $staff->id,
            'status' => EquipmentLoanStatus::Pending,
        ]);

        foreach ([$staff, $admin] as $viewer) {
            $result = $this->service->listFor($viewer, null);

            foreach (['viewer', 'items', 'overdue_summary', 'users', 'equipments'] as $key) {
                $this->assertArrayHasKey($key, $result, $viewer->role->value . ' のレスポンスに ' . $key . ' がありません');
            }
            $this->assertArrayHasKey('is_overdue', $result['items'][0]);
            $this->assertArrayHasKey('can_update_status', $result['items'][0]);
        }
    }
}
