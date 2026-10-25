<?php

namespace Modules\BloodBank\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BloodBank\Models\ComponentType;

/**
 * @extends Factory<ComponentType>
 */
final class ComponentTypeFactory extends Factory
{
    protected $model = ComponentType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('CMP_????'),
            'name' => fake()->words(2, true),
            'shelf_life_hours' => 24 * 35,
            'is_active' => true,
        ];
    }
}
