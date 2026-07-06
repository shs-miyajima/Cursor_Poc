<?php

namespace Tests\Feature;

use App\Enums\EquipmentLoanStatus;
use App\Models\EquipmentLoanRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentLoanStatusValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * PHPUnit-auth-005: ステータス未指定
     */
    public function test_ステータス未指定の場合は422でステータスは元の値のまま(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $loan = EquipmentLoanRequest::factory()->create(['status' => EquipmentLoanStatus::Pending]);

        $response = $this->patchJson("/api/equipment-loans/{$loan->id}/status", [
            'mock_user_id' => $admin->id,
        ]);

        $response->assertStatus(422);
        $this->assertSame(EquipmentLoanStatus::Pending, $loan->refresh()->status);
    }

    /**
     * PHPUnit-auth-006: 許可外ステータス
     */
    public function test_許可外ステータスの場合は422でステータスは元の値のまま(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $loan = EquipmentLoanRequest::factory()->create(['status' => EquipmentLoanStatus::Approved]);

        $response = $this->patchJson("/api/equipment-loans/{$loan->id}/status", [
            'mock_user_id' => $admin->id,
            'status' => 'pending',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.status.0', '指定されたステータスは更新できません');
        $this->assertSame(EquipmentLoanStatus::Approved, $loan->refresh()->status);
    }
}
