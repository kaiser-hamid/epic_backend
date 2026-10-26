<?php

namespace Modules\Shared\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Enums\BillingStatus;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Patient\Database\Factories\PatientMasterFactory;

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
            'amount' => 0,
            'status' => BillingStatus::Active,
            'billed_at' => now(),
            'created_by' => User::factory(),
        ];
    }
}
