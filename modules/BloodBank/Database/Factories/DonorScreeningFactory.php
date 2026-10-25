<?php

namespace Modules\BloodBank\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\DonorScreening;

/**
 * @extends Factory<DonorScreening>
 */
final class DonorScreeningFactory extends Factory
{
    protected $model = DonorScreening::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'case_donor_id' => CaseDonor::factory(),
            'weight_kg' => fake()->randomFloat(2, 50, 95),
            'height_cm' => fake()->randomFloat(1, 150, 190),
            'hemoglobin_g_dl' => fake()->randomFloat(1, 12.5, 17),
            'pre_pulse_bpm' => fake()->numberBetween(60, 100),
            'pre_bp_systolic' => fake()->numberBetween(100, 140),
            'pre_bp_diastolic' => fake()->numberBetween(60, 90),
            'pre_temperature_f' => fake()->randomFloat(1, 97, 99),
            'examined_by' => User::factory(),
            'examined_at' => now(),
        ];
    }

    public function withPostDonationVitals(): static
    {
        return $this->state(fn (): array => [
            'post_pulse_bpm' => fake()->numberBetween(60, 100),
            'post_bp_systolic' => fake()->numberBetween(100, 140),
            'post_bp_diastolic' => fake()->numberBetween(60, 90),
            'post_temperature_f' => fake()->randomFloat(1, 97, 99),
            'post_recorded_by' => User::factory(),
            'post_recorded_at' => now()->addMinutes(15),
        ]);
    }
}
