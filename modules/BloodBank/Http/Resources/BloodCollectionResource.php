<?php

namespace Modules\BloodBank\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\BloodBank\Data\BloodCollectionRow;
use Modules\BloodBank\Data\BloodCollectionView;
use Modules\BloodBank\Data\PlannedUnit;
use Modules\BloodBank\Data\SubjectView;
use Modules\BloodBank\Enums\SampleSubject;

/**
 * The blood collection screen: the information panel (`case`, `patient`, `invoice`) and one row per case donor
 * in sequence order. "Collect" is enabled while `is_collectable` (the donor is `eligible`, has no bag, and the case
 * still needs something). `collection` is the donor's bag, null until collected. `bag_plan` is what the next bag
 * is made into: patient rows first (`for_patient`), then stock rows; `needs_storage_location` marks the rows that
 * need a shelf, pre-filled from `default_location_type`. After a collect, `collected_collection_id` is the bag
 * just drawn and `collected_units` its units with `label_command`, logged as printed and sent to the printer.
 *
 * A stock row (`source = stock`, its unit in `stock_unit`) has no bag: "Print patient label" is enabled while
 * `can_reserve_stock_unit`. After it, `collected_units` holds that unit with its patient label `label_command`.
 *
 * A Therapeutic Phlebotomy case (`case.service`) has no donors: `can_record_phlebotomy` enables "Mark done", and
 * `phlebotomy` is the record once saved (volume, `pre` / `post` vitals or null, notes, who and when).
 *
 * @property-read BloodCollectionView $resource
 */
final class BloodCollectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'case' => CaseSummaryResource::make($this->resource->case)->toArray($request),
            'patient' => $this->resource->patient?->toArray(),
            'invoice' => $this->resource->invoice?->toArray(),
            'donors' => array_map(fn (BloodCollectionRow $row): array => [
                ...SubjectResource::make(new SubjectView(SampleSubject::Donor, caseDonor: $row->caseDonor))->toArray($request),
                'is_collectable' => $row->isCollectable,
                'can_reserve_stock_unit' => $row->canReserveStockUnit,
                'collection' => $row->collection === null ? null : [
                    'id' => $row->collection->id,
                    'bag_no' => $row->collection->bag_no,
                    'status' => $row->collection->status->value,
                    'notes' => $row->collection->notes,
                    'collected_at' => $row->collection->collected_at->toIso8601String(),
                    'collected_by' => ['id' => $row->collection->collector->id, 'name' => $row->collection->collector->name],
                ],
            ], $this->resource->donors),
            'bag_plan' => array_map(fn (PlannedUnit $planned): array => [
                'component_type_id' => $planned->componentType->id,
                'code' => $planned->componentType->code,
                'name' => $planned->componentType->name,
                'for_patient' => $planned->forPatient,
                'needs_storage_location' => $planned->needsStorageLocation,
                'default_location_type' => $planned->componentType->default_location_type?->value,
            ], $this->resource->bagPlan),
            'collected_collection_id' => $this->resource->collectedCollectionId,
            'collected_units' => UnitResource::collection($this->resource->collectedUnits)->resolve($request),
            'can_record_phlebotomy' => $this->resource->canRecordPhlebotomy,
            'phlebotomy' => $this->phlebotomy(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function phlebotomy(): ?array
    {
        $phlebotomy = $this->resource->case->phlebotomy;

        if ($phlebotomy === null) {
            return null;
        }

        $vitals = fn (string $moment): ?array => $phlebotomy->{"{$moment}_pulse_bpm"} === null ? null : [
            'pulse_bpm' => $phlebotomy->{"{$moment}_pulse_bpm"},
            'bp_systolic' => $phlebotomy->{"{$moment}_bp_systolic"},
            'bp_diastolic' => $phlebotomy->{"{$moment}_bp_diastolic"},
            'temperature_f' => $phlebotomy->{"{$moment}_temperature_f"} === null ? null : (float) $phlebotomy->{"{$moment}_temperature_f"},
        ];

        return [
            'id' => $phlebotomy->id,
            'volume_ml' => $phlebotomy->volume_ml,
            'pre' => $vitals('pre'),
            'post' => $vitals('post'),
            'notes' => $phlebotomy->notes,
            'performed_at' => $phlebotomy->performed_at->toIso8601String(),
            'performed_by' => ['id' => $phlebotomy->performer->id, 'name' => $phlebotomy->performer->name],
        ];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        if ($this->resource->collectedCollectionId !== null || $this->resource->collectedUnits !== []) {
            $response->setStatusCode(201);
        }
    }
}
