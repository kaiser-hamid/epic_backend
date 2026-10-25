<?php

namespace Modules\BloodBank\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BloodBank\Enums\UnitStatus;
use Modules\BloodBank\Models\Collection;
use Modules\BloodBank\Models\ComponentType;
use Modules\BloodBank\Models\Unit;
use Modules\BloodBank\Services\UnitNumberGenerator;
use Modules\Shared\Patient\Enums\BloodGroup;

/**
 * @extends Factory<Unit>
 */
final class UnitFactory extends Factory
{
    protected $model = Unit::class;

    /**
     * A source (whole-blood) unit whose unit number is its collection's bag number.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'collection_id' => Collection::factory(),
            'unit_no' => fn (array $attributes): string => (new UnitNumberGenerator)
                ->forUnit(Collection::findOrFail($attributes['collection_id'])->bag_no),
            'component_type_id' => ComponentType::factory(),
            'blood_group' => fake()->randomElement(BloodGroup::cases()),
            'volume_ml' => 350,
            'status' => UnitStatus::Available,
            'prepared_at' => now(),
            'expires_at' => now()->addDays(35),
        ];
    }

    /**
     * A component prepared from the given source unit, numbered as bag number plus component code.
     */
    public function component(Unit $parent, ?int $splitNo = null): self
    {
        return $this->state(fn (): array => [
            'parent_unit_id' => $parent->id,
            'collection_id' => $parent->collection_id,
            'unit_no' => fn (array $attributes): string => (new UnitNumberGenerator)->forUnit(
                Collection::findOrFail($parent->collection_id)->bag_no,
                ComponentType::findOrFail($attributes['component_type_id'])->code,
                $splitNo,
            ),
        ]);
    }
}
