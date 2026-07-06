<?php

namespace Tests\Unit\Services;

use App\Enums\EquipmentLoanStatus;
use App\Models\EquipmentLoanRequest;
use App\Services\EquipmentLoanPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EquipmentLoanPresenterTest extends TestCase
{
    use RefreshDatabase;

    private EquipmentLoanPresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->presenter = new EquipmentLoanPresenter();
    }

    /**
     * PHPUnit-dyn-007: 返却期限超過 true
     */
    public function test_approvedかつ返却期限が昨日以前の申請はis_overdueがtrueになる(): void
    {
        $loan = EquipmentLoanRequest::factory()->create([
            'status' => EquipmentLoanStatus::Approved,
            'requested_from' => Carbon::today()->subDays(5)->toDateString(),
            'requested_to' => Carbon::today()->subDay()->toDateString(),
        ]);

        $item = $this->presenter->toItemArray($loan->load(['user', 'equipment']));

        $this->assertTrue($item['is_overdue']);
    }

    /**
     * PHPUnit-dyn-008: 返却期限超過 false
     */
    public function test_approvedの未来日申請とreturned_pending_rejectedの過去日申請はいずれもis_overdueがfalseになる(): void
    {
        $futureApproved = EquipmentLoanRequest::factory()->create([
            'status' => EquipmentLoanStatus::Approved,
            'requested_from' => Carbon::today()->toDateString(),
            'requested_to' => Carbon::today()->addDays(3)->toDateString(),
        ]);

        $pastLoans = collect([
            EquipmentLoanStatus::Returned,
            EquipmentLoanStatus::Pending,
            EquipmentLoanStatus::Rejected,
        ])->map(fn (EquipmentLoanStatus $status) => EquipmentLoanRequest::factory()->create([
            'status' => $status,
            'requested_from' => Carbon::today()->subDays(5)->toDateString(),
            'requested_to' => Carbon::today()->subDay()->toDateString(),
        ]));

        $this->assertFalse(
            $this->presenter->toItemArray($futureApproved->load(['user', 'equipment']))['is_overdue'],
        );

        foreach ($pastLoans as $loan) {
            $this->assertFalse(
                $this->presenter->toItemArray($loan->load(['user', 'equipment']))['is_overdue'],
                $loan->status->value . ' の過去日申請が is_overdue = true になっています',
            );
        }
    }

    /**
     * PHPUnit-dyn-014: 返却申請中は返却期限超過対象
     */
    public function test_return_requestedかつ返却期限が昨日以前の申請はis_overdueがtrueになる(): void
    {
        $loan = EquipmentLoanRequest::factory()->create([
            'status' => EquipmentLoanStatus::ReturnRequested,
            'requested_from' => Carbon::today()->subDays(5)->toDateString(),
            'requested_to' => Carbon::today()->subDay()->toDateString(),
        ]);

        $item = $this->presenter->toItemArray($loan->load(['user', 'equipment']));

        $this->assertTrue($item['is_overdue']);
    }
}
