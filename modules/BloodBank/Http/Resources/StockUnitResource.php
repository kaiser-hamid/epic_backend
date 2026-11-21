<?php

namespace Modules\BloodBank\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\BloodBank\Models\Unit;

/**
 * A stock bag as the donor steps show it: the scan check's answer, and `stock_unit` of a stock row. Needs
 * `componentType` and `storageLocation` loaded.
 *
 * @mixin Unit
 */
final class StockUnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_no' => $this->unit_no,
            'status' => $this->status->value,
            'component' => ['id' => $this->componentType->id, 'code' => $this->componentType->code, 'name' => $this->componentType->name],
            'blood_group' => $this->blood_group?->value,
            'expires_at' => $this->expires_at->toIso8601String(),
            'storage_location' => $this->storageLocation === null ? null : [
                'id' => $this->storageLocation->id,
                'code' => $this->storageLocation->code,
                'name' => $this->storageLocation->name,
            ],
        ];
    }
}
