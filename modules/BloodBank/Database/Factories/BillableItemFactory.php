<?php

namespace Modules\BloodBank\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BloodBank\Enums\BillableItemKind;
use Modules\BloodBank\Enums\CaseService;
use Modules\BloodBank\Models\BillableItem;
use Modules\BloodBank\Models\ComponentType;
use Modules\Shared\TestCatalog\Database\Factories\TestItemFactory;
use Modules\Shared\TestCatalog\Models\TestItem;

/**
 * A Blood Bank catalog item with its meaning; by default a component item billing one unit of a new component
 * type.
 *
 * @extends Factory<BillableItem>
 */
final class BillableItemFactory extends Factory
{
    protected $model = BillableItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_item_id' => TestItemFactory::new()->bloodBank(),
            'test_item_code' => fn (array $attributes): string => TestItem::query()->findOrFail($attributes['test_item_id'])->code,
            'test_item_name' => fn (array $attributes): string => TestItem::query()->findOrFail($attributes['test_item_id'])->name,
            'kind' => BillableItemKind::Component,
            'component_type_id' => ComponentType::factory(),
            'units_per_quantity' => 1,
            'service' => null,
            'is_active' => true,
        ];
    }

    /**
     * Bills `$unitsPerQuantity` units of the component type per billed quantity.
     */
    public function component(ComponentType $componentType, int $unitsPerQuantity = 1): self
    {
        return $this->state([
            'kind' => BillableItemKind::Component,
            'component_type_id' => $componentType->id,
            'units_per_quantity' => $unitsPerQuantity,
            'service' => null,
        ]);
    }

    public function procedure(CaseService $service): self
    {
        return $this->state([
            'kind' => BillableItemKind::Procedure,
            'component_type_id' => null,
            'service' => $service,
        ]);
    }

    public function storageFee(): self
    {
        return $this->state([
            'kind' => BillableItemKind::StorageFee,
            'component_type_id' => null,
            'service' => null,
        ]);
    }
}
