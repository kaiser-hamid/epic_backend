<?php

namespace Modules\Shared\TestCatalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shared\TestCatalog\Models\TestItem;

/**
 * A billable test. `rate` is integer paisa, shown for reference only; the bill always snapshots it server-side.
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
            'rate' => $this->rate,
        ];
    }
}
