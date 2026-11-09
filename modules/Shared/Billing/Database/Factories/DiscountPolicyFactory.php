<?php

namespace Modules\Shared\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Shared\Billing\Enums\DiscountType;
use Modules\Shared\Billing\Models\DiscountPolicy;

/**
 * @extends Factory<DiscountPolicy>
 */
final class DiscountPolicyFactory extends Factory
{
    protected $model = DiscountPolicy::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('DP_????'),
            'name' => fake()->words(2, true),
            'type' => DiscountType::Percentage,
            'rate_bp' => 1000,
            'is_active' => true,
        ];
    }

    public function percent(int $percent): self
    {
        return $this->state(['rate_bp' => $percent * 100]);
    }
}
