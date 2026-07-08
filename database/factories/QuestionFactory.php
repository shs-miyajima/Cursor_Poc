<?php

namespace Database\Factories;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\Survey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'survey_id' => Survey::factory(),
            'body' => fake()->sentence(),
            'type' => QuestionType::Single,
            'is_required' => false,
            'sort_order' => 1,
        ];
    }

    public function text(): static
    {
        return $this->state(fn () => ['type' => QuestionType::Text]);
    }

    public function multiple(): static
    {
        return $this->state(fn () => ['type' => QuestionType::Multiple]);
    }

    public function required(): static
    {
        return $this->state(fn () => ['is_required' => true]);
    }
}
