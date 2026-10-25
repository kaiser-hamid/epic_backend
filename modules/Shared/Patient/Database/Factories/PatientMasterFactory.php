<?php

namespace Modules\Shared\Patient\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Shared\Patient\Enums\BloodGroup;
use Modules\Shared\Patient\Enums\Gender;
use Modules\Shared\Patient\Enums\MaritalStatus;
use Modules\Shared\Patient\Enums\PatientTitle;
use Modules\Shared\Patient\Models\PatientMaster;

/**
 * @extends Factory<PatientMaster>
 */
final class PatientMasterFactory extends Factory
{
    protected $model = PatientMaster::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_no' => fake()->unique()->numerify('PT-######'),
            'title' => PatientTitle::Mr,
            'name' => fake()->name(),
            'address' => fake()->address(),
            'age_years' => fake()->numberBetween(1, 90),
            'age_months' => fake()->numberBetween(0, 11),
            'age_days' => fake()->numberBetween(0, 30),
            'gender' => fake()->randomElement(Gender::cases()),
            'marital_status' => fake()->randomElement(MaritalStatus::cases()),
            'blood_group' => fake()->randomElement(BloodGroup::cases()),
            'phone' => fake()->numerify('01#########'),
            'created_by' => User::factory(),
        ];
    }
}
