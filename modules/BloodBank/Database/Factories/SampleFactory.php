<?php

namespace Modules\BloodBank\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BloodBank\Enums\SampleStatus;
use Modules\BloodBank\Enums\SampleSubject;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Sample;

/**
 * @extends Factory<Sample>
 */
final class SampleFactory extends Factory
{
    protected $model = Sample::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sample_no' => fake()->unique()->numerify('BB-S-######'),
            'case_id' => PatientCase::factory(),
            'subject_type' => SampleSubject::Patient,
            'collected_at' => now(),
            'collected_by' => User::factory(),
            'status' => SampleStatus::Collected,
        ];
    }
}
