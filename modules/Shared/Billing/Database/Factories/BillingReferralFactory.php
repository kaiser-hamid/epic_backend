<?php

namespace Modules\Shared\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Shared\Billing\Models\BillingReferral;
use Modules\Shared\Doctor\Database\Factories\DoctorFactory;

/**
 * @extends Factory<BillingReferral>
 */
final class BillingReferralFactory extends Factory
{
    protected $model = BillingReferral::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'billing_id' => BillingFactory::new(),
            'doctor_id' => DoctorFactory::new(),
            'is_self_referral' => false,
        ];
    }

    public function selfReferral(): static
    {
        return $this->state(fn (array $attributes) => [
            'doctor_id' => null,
            'is_self_referral' => true,
        ]);
    }
}
