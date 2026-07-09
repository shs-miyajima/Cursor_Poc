<?php

namespace Tests\Feature\Http\Concerns;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

trait HttpTestHelpers
{
    /**
     * @return array{0: Company, 1: User}
     */
    protected function createCompanyWithAdmin(array $companyOverrides = [], array $adminOverrides = []): array
    {
        $company = Company::factory()->create($companyOverrides);
        $admin = User::factory()->admin()->create(array_merge([
            'company_id' => $company->id,
            'password' => Hash::make('pass12345'),
        ], $adminOverrides));

        return [$company, $admin];
    }

    /**
     * @return array<string, int>
     */
    protected function sessionForCompany(Company $company): array
    {
        return ['company_context.company_id' => $company->id];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validUserPayload(Company $company, array $overrides = []): array
    {
        return array_merge([
            'role' => 'user',
            'name' => 'テストユーザー',
            'email' => 'newuser'.uniqid().'@x.jp',
            'password' => 'pass12345',
            'department_id' => '',
            'gender' => 'no_answer',
            'birth_date' => '',
            'hired_month' => '',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validSurveyPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'テストアンケート',
            'description' => '',
            'deadline_at' => '',
            'action' => 'publish',
            'questions' => [
                [
                    'body' => '設問1',
                    'type' => 'single',
                    'is_required' => '1',
                    'options' => ['選択肢A', '選択肢B'],
                ],
            ],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $questionOverrides
     * @return list<array<string, mixed>>
     */
    protected function makeQuestions(int $count, array $questionOverrides = []): array
    {
        $questions = [];

        for ($i = 0; $i < $count; $i++) {
            $questions[] = array_merge([
                'body' => '設問'.($i + 1),
                'type' => 'single',
                'is_required' => '1',
                'options' => ['A', 'B'],
            ], $questionOverrides);
        }

        return $questions;
    }

    /**
     * @param  array<string, mixed>  $questionOverrides
     * @return list<array<string, mixed>>
     */
    protected function makeQuestionsWithOptions(int $optionCount, array $questionOverrides = []): array
    {
        $labels = [];
        for ($i = 0; $i < $optionCount; $i++) {
            $labels[] = '選択肢'.($i + 1);
        }

        return [
            array_merge([
                'body' => '設問1',
                'type' => 'single',
                'is_required' => '1',
                'options' => $labels,
            ], $questionOverrides),
        ];
    }
}
