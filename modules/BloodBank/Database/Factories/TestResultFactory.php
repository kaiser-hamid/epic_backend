<?php

namespace Modules\BloodBank\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BloodBank\Enums\TestResultStatus;
use Modules\BloodBank\Models\Sample;
use Modules\BloodBank\Models\TestDefinition;
use Modules\BloodBank\Models\TestResult;

/**
 * @extends Factory<TestResult>
 */
final class TestResultFactory extends Factory
{
    protected $model = TestResult::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sample_id' => Sample::factory(),
            'test_definition_id' => TestDefinition::factory(),
            'version' => 1,
            'is_current' => true,
            'result_value' => fake()->word(),
            'tested_by' => User::factory(),
            'tested_at' => now(),
            'status' => TestResultStatus::Entered,
        ];
    }
}
