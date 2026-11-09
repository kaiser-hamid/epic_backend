<?php

namespace Modules\BloodBank\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BloodBank\Models\ComponentType;
use Modules\Shared\TestCatalog\Database\Factories\TestItemFactory;
use Modules\Shared\TestCatalog\Models\TestItem;

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
            'test_item_id' => TestItemFactory::new()->bloodBank(),
            'test_item_code' => fn (array $attributes): string => TestItem::query()->findOrFail($attributes['test_item_id'])->code,
            'test_item_name' => fn (array $attributes): string => TestItem::query()->findOrFail($attributes['test_item_id'])->name,
            'code' => fake()->unique()->lexify('CMP_????'),
            'unit_prefix' => 'B'.fake()->unique()->lexify('???'),
            'name' => fake()->words(2, true),
            'shelf_life_hours' => 24 * 35,
            'is_active' => true,
        ];
    }
}
