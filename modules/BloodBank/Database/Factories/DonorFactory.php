<?php

namespace Modules\BloodBank\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BloodBank\Models\Donor;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Patient\Enums\BloodGroup;
use Modules\Shared\Patient\Enums\Gender;

/**
 * @extends Factory<Donor>
 */
final class DonorFactory extends Factory
{
    protected $model = Donor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'donor_no' => fake()->unique()->numerify('BB-D-######'),
            'name' => fake()->name(),
            'date_of_birth' => fake()->dateTimeBetween('-60 years', '-18 years'),
            'gender' => fake()->randomElement(Gender::cases()),
            'blood_group' => fake()->randomElement(BloodGroup::cases()),
            'phone' => fake()->numerify('01#########'),
            'address' => fake()->address(),
            'created_by' => User::factory(),
        ];
    }
}
