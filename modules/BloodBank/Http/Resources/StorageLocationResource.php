<?php

namespace Modules\BloodBank\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\BloodBank\Models\StorageLocation;

/**
 * A storage location of the branch as a dropdown option.
 *
 * @mixin StorageLocation
 */
final class StorageLocationResource extends JsonResource
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
            'type' => $this->type->value,
            'min_temperature_c' => $this->min_temperature_c,
            'max_temperature_c' => $this->max_temperature_c,
        ];
    }
}
