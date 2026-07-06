<?php

namespace Database\Factories;

use App\Enums\EquipmentLoanStatus;
use App\Models\Equipment;
use App\Models\EquipmentLoanRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<EquipmentLoanRequest>
 */
class EquipmentLoanRequestFactory extends Factory
{
    protected $model = EquipmentLoanRequest::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'equipment_id' => Equipment::factory(),
            'status' => EquipmentLoanStatus::Pending,
            'requested_from' => Carbon::today()->addDays(7)->toDateString(),
            'requested_to' => Carbon::today()->addDays(9)->toDateString(),
            'reason' => null,
        ];
    }
}
