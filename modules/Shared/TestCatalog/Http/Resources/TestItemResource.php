<?php

namespace Modules\Shared\TestCatalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shared\TestCatalog\Models\TestItem;

/**
 * A billable test. `rate` is integer paisa, shown for reference only; the bill always snapshots it server-side.
 * `discount_percent` overrides the bill's discount policy for this item (null follows the policy).
 *
 * @mixin TestItem
 */
final class TestItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'department_id' => $this->department_id,
            'department_name' => $this->department_name,
            'category' => $this->category->value,
            'rate' => $this->rate,
            'discount_percent' => $this->discount_rate_bp === null ? null : $this->discount_rate_bp / 100,
        ];
    }
}
