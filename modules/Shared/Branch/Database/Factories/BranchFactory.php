<?php

namespace Modules\Shared\Branch\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Shared\Branch\Models\Branch;

/**
 * @extends Factory<Branch>
 */
final class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('??')),
            'name' => fake()->city().' Branch',
            'address' => fake()->address(),
            'phone' => fake()->numerify('01#########'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
