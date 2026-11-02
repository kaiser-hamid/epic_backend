<?php

namespace Modules\BloodBank\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BloodBank\Enums\StorageLocationType;
use Modules\BloodBank\Models\StorageLocation;
use Modules\Shared\Branch\Models\Branch;

/**
 * @extends Factory<StorageLocation>
 */
final class StorageLocationFactory extends Factory
{
    protected $model = StorageLocation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'code' => fake()->unique()->lexify('LOC-????'),
            'name' => fake()->words(2, true),
            'type' => StorageLocationType::Refrigerator,
            'min_temperature_c' => 2,
            'max_temperature_c' => 6,
            'is_active' => true,
        ];
    }
}
