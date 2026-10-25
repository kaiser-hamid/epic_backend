<?php

namespace Modules\Shared\TestCatalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Shared\TestCatalog\Models\Department;
use Modules\Shared\TestCatalog\Models\TestItem;

/**
 * @extends Factory<TestItem>
 */
final class TestItemFactory extends Factory
{
    protected $model = TestItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => DepartmentFactory::new(),
            'code' => fake()->unique()->numerify('ITEM-#####'),
            'name' => fake()->unique()->words(3, true),
            'department_name' => fn (array $attributes): string => Department::findOrFail($attributes['department_id'])->name,
            'rate' => fake()->numberBetween(100, 5000) * 100,
            'is_active' => true,
        ];
    }
}
