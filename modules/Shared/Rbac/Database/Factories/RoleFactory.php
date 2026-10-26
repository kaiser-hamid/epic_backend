<?php

namespace Modules\Shared\Rbac\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Shared\Rbac\Models\Role;

/**
 * @extends Factory<Role>
 */
final class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle(),
            'description' => fake()->sentence(),
        ];
    }
}
