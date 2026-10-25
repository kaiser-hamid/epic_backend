<?php

namespace Modules\BloodBank\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BloodBank\Models\CaseComponent;
use Modules\BloodBank\Models\ComponentType;
use Modules\BloodBank\Models\PatientCase;

/**
 * @extends Factory<CaseComponent>
 */
final class CaseComponentFactory extends Factory
{
    protected $model = CaseComponent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'case_id' => PatientCase::factory(),
            'component_type_id' => ComponentType::factory(),
            'quantity' => fake()->numberBetween(1, 3),
        ];
    }
}
