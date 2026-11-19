<?php

namespace Modules\BloodBank\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\BloodBank\Database\Factories\BillableItemFactory;
use Modules\BloodBank\Enums\BillableItemKind;
use Modules\BloodBank\Enums\CaseService;

/**
 * What a Blood Bank catalog item (`test_item_id`, code and name copied) means to Blood Bank: a component
 * (`component_type_id`, `units_per_quantity` units per billed quantity), a procedure (`service`) or the storage
 * fee. Catalog items without a row are plain services.
 */
#[Table('bb_billable_items')]
#[Fillable(['test_item_id', 'test_item_code', 'test_item_name', 'kind', 'component_type_id', 'units_per_quantity', 'service', 'is_active'])]
#[UseFactory(BillableItemFactory::class)]
final class BillableItem extends Model
{
    /** @use HasFactory<BillableItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => BillableItemKind::class,
            'service' => CaseService::class,
            'units_per_quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function componentType(): BelongsTo
    {
        return $this->belongsTo(ComponentType::class, 'component_type_id');
    }
}
