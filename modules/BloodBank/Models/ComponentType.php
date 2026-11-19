<?php

namespace Modules\BloodBank\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\BloodBank\Database\Factories\ComponentTypeFactory;
use Modules\BloodBank\Enums\StorageLocationType;

/**
 * A blood component (WB, PCV, FFP…). Its own short `code`, `unit_prefix` and shelf life drive preparation and
 * labels; the catalog items that bill it are `bb_billable_items` rows. What a bag is made into is decided by
 * `BagPlan` (WB and platelet apheresis keep the bag as one unit).
 */
#[Table('bb_component_types')]
#[Fillable(['code', 'unit_prefix', 'name', 'shelf_life_hours', 'storage_condition', 'default_location_type', 'is_active'])]
#[UseFactory(ComponentTypeFactory::class)]
final class ComponentType extends Model
{
    /** @use HasFactory<ComponentTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_location_type' => StorageLocationType::class,
            'is_active' => 'boolean',
        ];
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'component_type_id');
    }

    public function billableItems(): HasMany
    {
        return $this->hasMany(BillableItem::class, 'component_type_id');
    }
}
