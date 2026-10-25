<?php

namespace Modules\BloodBank\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\Donor;
use Modules\BloodBank\Models\PatientCase;

/**
 * @extends Factory<CaseDonor>
 */
final class CaseDonorFactory extends Factory
{
    protected $model = CaseDonor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'case_id' => PatientCase::factory(),
            'donor_id' => Donor::factory(),
            'sequence_no' => 1,
            'status' => CaseDonorStatus::Registered,
        ];
    }
}
