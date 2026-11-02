<?php

namespace Modules\BloodBank\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\BloodBank\Data\BloodCollectionRow;
use Modules\BloodBank\Data\BloodCollectionView;
use Modules\BloodBank\Data\SubjectView;
use Modules\BloodBank\Enums\SampleSubject;

/**
 * The blood collection screen: the information panel (`case`, `patient`, `invoice`) and one row per case donor
 * in sequence order. "Collect" is enabled while `is_collectable` (the donor is `eligible` and has no bag).
 * `collection` is the donor's bag, null until collected, with `label_print_count` (0 = label never printed, so
 * the next print needs no reason). After a collect, `collected_collection_id` is the bag just drawn so its label
 * can be printed straight away.
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
                'collection' => $row->collection === null ? null : [
                    'id' => $row->collection->id,
                    'bag_no' => $row->collection->bag_no,
                    'status' => $row->collection->status->value,
                    'volume_ml' => $row->collection->volume_ml,
                    'notes' => $row->collection->notes,
                    'collected_at' => $row->collection->collected_at->toIso8601String(),
                    'collected_by' => ['id' => $row->collection->collector->id, 'name' => $row->collection->collector->name],
                    'label_print_count' => $row->labelPrintCount,
                ],
            ], $this->resource->donors),
            'collected_collection_id' => $this->resource->collectedCollectionId,
        ];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        if ($this->resource->collectedCollectionId !== null) {
            $response->setStatusCode(201);
        }
    }
}
