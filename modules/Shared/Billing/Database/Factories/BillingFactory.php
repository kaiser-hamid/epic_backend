<?php

namespace Modules\Shared\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Enums\BillingStatus;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Billing\Models\PatientType;
use Modules\Shared\Branch\Database\Factories\BranchFactory;
use Modules\Shared\Patient\Database\Factories\PatientMasterFactory;
use Modules\Shared\TestCatalog\Enums\TestItemCategory;

/**
 * @extends Factory<Billing>
 */
final class BillingFactory extends Factory
{
    protected $model = Billing::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_no' => fake()->unique()->numerify('INV-########'),
            'patient_master_id' => PatientMasterFactory::new(),
            'branch_id' => BranchFactory::new(),
            'category' => TestItemCategory::General,
            'patient_type_id' => fn (): int => PatientType::query()->where('code', PatientType::GENERAL)->value('id'),
            'subtotal_amount' => 0,
            'amount' => 0,
            'status' => BillingStatus::Active,
            'billed_at' => now(),
            'created_by' => User::factory(),
        ];
    }

    public function bloodBank(): self
    {
        return $this->state(['category' => TestItemCategory::BloodBank]);
    }
}
