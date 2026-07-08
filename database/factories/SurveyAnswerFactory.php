<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\SurveyAnswer;
use App\Models\SurveyResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SurveyAnswer>
 */
class SurveyAnswerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'survey_response_id' => SurveyResponse::factory(),
            'question_id' => Question::factory(),
            'question_option_id' => null,
            'text_value' => null,
        ];
    }
}
