<?php

namespace Database\Factories;

use App\Models\Equipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipment>
 */
class EquipmentFactory extends Factory
{
    protected $model = Equipment::class;

    public function definition(): array
    {
        return [
            'name' => 'テスト備品 ' . fake()->unique()->numberBetween(1, 9999),
            'stock_count' => 1,
            'target_department' => null,
        ];
    }
}
