<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EquipmentLoanRequestValidationTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(User $user, Equipment $equipment): array
    {
        return [
            'mock_user_id' => $user->id,
            'equipment_id' => $equipment->id,
            'requested_from' => Carbon::today()->addDays(7)->toDateString(),
            'requested_to' => Carbon::today()->addDays(9)->toDateString(),
            'reason' => 'テスト用',
        ];
    }

    /**
     * PHPUnit-inp-005: equipment_id 正常指定
     */
    public function test_存在する備品IDを指定するとバリデーションを通過し申請が登録される(): void
    {
        $user = User::factory()->create(['department' => '開発部']);
        $equipment = Equipment::factory()->create(['target_department' => null]);

        $response = $this->postJson('/api/equipment-loans', $this->validPayload($user, $equipment));

        $response->assertCreated();
        $this->assertDatabaseCount('equipment_loan_requests', 1);
    }

    /**
     * PHPUnit-inp-006: 貸出開始日 過去日
     */
    public function test_貸出開始日が過去日の場合は422でエラーメッセージが返る(): void
    {
        $user = User::factory()->create();
        $equipment = Equipment::factory()->create();
        $payload = $this->validPayload($user, $equipment);
        $payload['requested_from'] = Carbon::yesterday()->toDateString();

        $response = $this->postJson('/api/equipment-loans', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('errors.requested_from.0', '貸出開始日は本日以降の日付を入力してください');
        $this->assertDatabaseCount('equipment_loan_requests', 0);
    }

    /**
     * PHPUnit-inp-007: 貸出終了日が開始日より前
     */
    public function test_貸出終了日が開始日より前の場合は422でエラーメッセージが返る(): void
    {
        $user = User::factory()->create();
        $equipment = Equipment::factory()->create();
        $payload = $this->validPayload($user, $equipment);
        $payload['requested_from'] = Carbon::today()->addDays(9)->toDateString();
        $payload['requested_to'] = Carbon::today()->addDays(7)->toDateString();

        $response = $this->postJson('/api/equipment-loans', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('errors.requested_to.0', '貸出終了日は貸出開始日以降の日付を入力してください');
        $this->assertDatabaseCount('equipment_loan_requests', 0);
    }

    /**
     * PHPUnit-inp-008: mock_user_id 未指定
     */
    public function test_操作ユーザー未指定の場合は422でエラーメッセージが返る(): void
    {
        $user = User::factory()->create();
        $equipment = Equipment::factory()->create();
        $payload = $this->validPayload($user, $equipment);
        unset($payload['mock_user_id']);

        $listResponse = $this->getJson('/api/equipment-loans');
        $storeResponse = $this->postJson('/api/equipment-loans', $payload);

        $listResponse->assertStatus(422)
            ->assertJsonPath('errors.mock_user_id.0', '操作ユーザーを選択してください');
        $storeResponse->assertStatus(422)
            ->assertJsonPath('errors.mock_user_id.0', '操作ユーザーを選択してください');
        $this->assertDatabaseCount('equipment_loan_requests', 0);
    }

    /**
     * PHPUnit-inp-010: equipment_id 未指定
     */
    public function test_備品未指定の場合は422でエラーメッセージが返る(): void
    {
        $user = User::factory()->create();
        $equipment = Equipment::factory()->create();
        $payload = $this->validPayload($user, $equipment);
        unset($payload['equipment_id']);

        $response = $this->postJson('/api/equipment-loans', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('errors.equipment_id.0', '備品を選択してください');
        $this->assertDatabaseCount('equipment_loan_requests', 0);
    }

    /**
     * PHPUnit-inp-011: equipment_id 存在なし
     */
    public function test_存在しない備品IDの場合は404でエラーメッセージが返る(): void
    {
        $user = User::factory()->create();
        $equipment = Equipment::factory()->create();
        $payload = $this->validPayload($user, $equipment);
        $payload['equipment_id'] = 999999;

        $response = $this->postJson('/api/equipment-loans', $payload);

        $response->assertNotFound()
            ->assertJsonPath('message', '備品が見つかりません');
        $this->assertDatabaseCount('equipment_loan_requests', 0);
    }
}
