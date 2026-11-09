<?php

namespace Modules\Shared\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Shared\Billing\Models\PatientType;

/**
 * @extends Factory<PatientType>
 */
final class PatientTypeFactory extends Factory
{
    protected $model = PatientType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('PT_????'),
            'name' => fake()->words(2, true),
            'is_active' => true,
        ];
    }
}
