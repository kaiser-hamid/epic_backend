<?php

namespace Modules\Shared\TestCatalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Shared\TestCatalog\Enums\DepartmentType;
use Modules\Shared\TestCatalog\Models\Department;

/**
 * @extends Factory<Department>
 */
final class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('DEPT-????'),
            'name' => fake()->unique()->words(2, true),
            'type' => DepartmentType::Lab,
            'is_active' => true,
        ];
    }

    public function nonLab(): self
    {
        return $this->state(['type' => DepartmentType::NonLab]);
    }
}
