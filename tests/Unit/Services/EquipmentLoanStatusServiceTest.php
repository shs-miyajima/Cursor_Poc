<?php

namespace Tests\Unit\Services;

use App\Enums\EquipmentLoanStatus;
use App\Models\EquipmentLoanRequest;
use App\Models\User;
use App\Services\EquipmentLoanStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class EquipmentLoanStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    private EquipmentLoanStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EquipmentLoanStatusService();
    }

    /**
     * PHPUnit-auth-001: 一般社員 ステータス更新不可
     */
    public function test_一般社員がステータス更新すると403相当の例外になりステータスは変わらない(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $loan = EquipmentLoanRequest::factory()->create(['status' => EquipmentLoanStatus::Pending]);

        try {
            $this->service->update($staff, $loan, EquipmentLoanStatus::Approved);
            $this->fail('AccessDeniedHttpException が発生していません');
        } catch (AccessDeniedHttpException $e) {
            $this->assertSame('ステータスを更新する権限がありません', $e->getMessage());
        }

        $this->assertSame(EquipmentLoanStatus::Pending, $loan->refresh()->status);
    }

    /**
     * PHPUnit-auth-002: 管理者 ステータス更新可（承認）
     */
    public function test_管理者はpending申請をapprovedに更新できる(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $loan = EquipmentLoanRequest::factory()->create(['status' => EquipmentLoanStatus::Pending]);

        $updated = $this->service->update($admin, $loan, EquipmentLoanStatus::Approved);

        $this->assertSame(EquipmentLoanStatus::Approved, $updated->status);
    }

    /**
     * PHPUnit-auth-003: 管理者 却下更新可
     */
    public function test_管理者はpending申請をrejectedに更新できる(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $loan = EquipmentLoanRequest::factory()->create(['status' => EquipmentLoanStatus::Pending]);

        $updated = $this->service->update($admin, $loan, EquipmentLoanStatus::Rejected);

        $this->assertSame(EquipmentLoanStatus::Rejected, $updated->status);
    }

    /**
     * PHPUnit-auth-004: 管理者 返却更新可
     */
    public function test_管理者はapproved申請をreturnedに更新できる(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $loan = EquipmentLoanRequest::factory()->create(['status' => EquipmentLoanStatus::Approved]);

        $updated = $this->service->update($admin, $loan, EquipmentLoanStatus::Returned);

        $this->assertSame(EquipmentLoanStatus::Returned, $updated->status);
    }
}
