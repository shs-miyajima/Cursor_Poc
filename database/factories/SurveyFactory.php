<?php

namespace Database\Factories;

use App\Enums\SurveyStatus;
use App\Models\Company;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Survey>
 */
class SurveyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'title' => fake()->sentence(3),
            'description' => null,
            'status' => SurveyStatus::Draft,
            'deadline_at' => null,
            'created_by' => User::factory()->admin(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => SurveyStatus::Published]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => SurveyStatus::Closed]);
    }
}
