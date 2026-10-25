<?php

namespace Modules\BloodBank\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BloodBank\Models\Consumable;

/**
 * @extends Factory<Consumable>
 */
final class ConsumableFactory extends Factory
{
    protected $model = Consumable::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('CON-????'),
            'name' => fake()->words(2, true),
            'unit_of_measure' => 'pcs',
            'minimum_stock' => 10,
            'current_balance' => 0,
            'tracks_batch' => false,
            'tracks_expiry' => false,
            'is_active' => true,
        ];
    }
}
