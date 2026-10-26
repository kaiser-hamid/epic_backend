<?php

namespace Modules\BloodBank\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BloodBank\Enums\CrossmatchResult;
use Modules\BloodBank\Enums\TestResultStatus;
use Modules\BloodBank\Models\Crossmatch;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Sample;
use Modules\BloodBank\Models\Unit;
use Modules\Shared\Auth\Models\User;

/**
 * @extends Factory<Crossmatch>
 */
final class CrossmatchFactory extends Factory
{
    protected $model = Crossmatch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'case_id' => PatientCase::factory(),
            'patient_sample_id' => fn (array $attributes) => Sample::factory()->create(['case_id' => $attributes['case_id']]),
            'unit_id' => Unit::factory(),
            'version' => 1,
            'is_current' => true,
            'major_result' => CrossmatchResult::Compatible,
            'minor_result' => CrossmatchResult::Compatible,
            'tested_by' => User::factory(),
            'tested_at' => now(),
            'status' => TestResultStatus::Entered,
        ];
    }
}
