<?php

namespace Modules\BloodBank\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BloodBank\Models\Consumable;
use Modules\BloodBank\Models\ConsumableBatch;

/**
 * @extends Factory<ConsumableBatch>
 */
final class ConsumableBatchFactory extends Factory
{
    protected $model = ConsumableBatch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consumable_id' => Consumable::factory(),
            'batch_no' => fake()->unique()->bothify('LOT-####??'),
            'expires_at' => now()->addYear(),
            'quantity_on_hand' => 0,
        ];
    }
}
