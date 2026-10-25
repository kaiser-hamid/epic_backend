<?php

namespace Modules\BloodBank\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BloodBank\Enums\TestAppliesTo;
use Modules\BloodBank\Enums\TestResultType;
use Modules\BloodBank\Models\TestDefinition;

/**
 * @extends Factory<TestDefinition>
 */
final class TestDefinitionFactory extends Factory
{
    protected $model = TestDefinition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('TEST_????'),
            'name' => fake()->words(2, true),
            'result_type' => TestResultType::Text,
            'applies_to' => TestAppliesTo::Both,
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }
}
