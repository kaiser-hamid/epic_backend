<?php

namespace Modules\BloodBank\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection as SupportCollection;
use Modules\BloodBank\Models\Collection;
use Modules\BloodBank\Models\Unit;

/**
 * One unit of a donation, as listed with the donation: `unit_no` (the number on its label), its component code and
 * status. A donation has no number of its own, so screens show these unit numbers instead.
 *
 * @property-read Unit $resource
 */
final class DonationUnitResource extends JsonResource
{
    /**
     * The units of a donation in creation order (needs `units.componentType` loaded).
     *
     * @return list<array<string, mixed>>
     */
    public static function forCollection(Collection $collection, Request $request): array
    {
        /** @var SupportCollection<int, Unit> $units */
        $units = $collection->units->sortBy('id')->values();

        return array_values(self::collection($units)->resolve($request));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'unit_no' => $this->resource->unit_no,
            'component_code' => $this->resource->componentType->code,
            'status' => $this->resource->status->value,
        ];
    }
}
