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
 * labels; the catalog items that bill it are `bb_billable_items` rows. A whole-bag type (`is_whole_bag`: WB,
 * platelet apheresis) is the bag kept as one product and cannot be combined with other components.
 */
#[Table('bb_component_types')]
#[Fillable(['code', 'unit_prefix', 'name', 'shelf_life_hours', 'storage_condition', 'default_location_type', 'is_whole_bag', 'is_active'])]
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
            'is_whole_bag' => 'boolean',
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
