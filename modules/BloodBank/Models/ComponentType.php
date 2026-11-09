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
 * A blood component (WB, PCV, FP…), created from the Blood Bank catalog item that bills it: `test_item_id` with the
 * item's code and name copied. Its own short `code`, `unit_prefix` and shelf life drive preparation and labels.
 */
#[Table('bb_component_types')]
#[Fillable([
    'test_item_id', 'test_item_code', 'test_item_name', 'code', 'unit_prefix', 'name', 'shelf_life_hours', 'storage_condition',
    'default_location_type', 'is_active',
])]
#[UseFactory(ComponentTypeFactory::class)]
final class ComponentType extends Model
{
    /** @use HasFactory<ComponentTypeFactory> */
    use HasFactory;

    /**
     * Whole blood: a bag kept as it is; its unit no is the bag no with this type's prefix.
     */
    public const WHOLE_BLOOD = 'WB';

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
}
