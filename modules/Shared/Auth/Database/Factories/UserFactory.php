<?php

namespace Modules\Shared\Auth\Database\Factories;

use Modules\Shared\Auth\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => fake()->unique()->numerify('EMP-####'),
            'name' => fake()->name(),
            'designation' => fake()->jobTitle(),
            'username' => fake()->unique()->userName(),
            'phone' => fake()->numerify('01#########'),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'is_super_admin' => false,
            'must_change_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the employee account is deactivated.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * An IT/Developer account that bypasses branch and permission checks.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_super_admin' => true,
        ]);
    }

    /**
     * The account must change its password before doing anything else.
     */
    public function mustChangePassword(): static
    {
        return $this->state(fn (array $attributes) => [
            'must_change_password' => true,
        ]);
    }
}
