<?php

namespace Modules\Shared\Doctor\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Doctor\Models\Doctor;

/**
 * @extends Factory<Doctor>
 */
final class DoctorFactory extends Factory
{
    protected $model = Doctor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('DR-####'),
            'name' => 'Dr. '.fake()->name(),
            'degree' => 'MBBS',
            'address' => fake()->address(),
            'phone' => fake()->numerify('01#########'),
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
