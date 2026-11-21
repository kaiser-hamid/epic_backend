<?php

namespace Modules\BloodBank\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BloodBank\Enums\UnitStatus;
use Modules\BloodBank\Models\Collection;
use Modules\BloodBank\Models\ComponentType;
use Modules\BloodBank\Models\Unit;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Patient\Enums\BloodGroup;

/**
 * @extends Factory<Unit>
 */
final class UnitFactory extends Factory
{
    protected $model = Unit::class;

    /**
     * A component bag numbered as UnitNumber does: the component type's prefix, then the donation's digits.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'collection_id' => Collection::factory(),
            'component_type_id' => ComponentType::factory(),
            'unit_no' => fn (array $attributes): string => ComponentType::findOrFail($attributes['component_type_id'])->unit_prefix
                .fake()->unique()->numerify('##########'),
            'blood_group' => fake()->randomElement(BloodGroup::cases()),
            'volume_ml' => 350,
            'status' => UnitStatus::Available,
            'expires_at' => now()->addDays(35),
        ];
    }
}
