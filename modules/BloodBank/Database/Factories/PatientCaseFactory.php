<?php

namespace Modules\BloodBank\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BloodBank\Enums\CareType;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Models\PatientCase;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Database\Factories\BillingFactory;
use Modules\Shared\Branch\Database\Factories\BranchFactory;
use Modules\Shared\Patient\Database\Factories\PatientMasterFactory;

/**
 * @extends Factory<PatientCase>
 */
final class PatientCaseFactory extends Factory
{
    protected $model = PatientCase::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'case_no' => fake()->unique()->numerify('BB-CASE-######'),
            'patient_master_id' => PatientMasterFactory::new(),
            'branch_id' => BranchFactory::new(),
            'billing_id' => fn (array $attributes): int => BillingFactory::new()->bloodBank()->create([
                'patient_master_id' => $attributes['patient_master_id'],
                'branch_id' => $attributes['branch_id'],
            ])->id,
            'care_type' => fake()->randomElement(CareType::cases()),
            'status' => CaseStatus::Open,
            'created_by' => User::factory(),
        ];
    }
}
