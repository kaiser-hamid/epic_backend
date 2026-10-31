<?php

namespace Modules\BloodBank\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\BloodBank\Data\CollectionSubject;
use Modules\BloodBank\Data\SampleCollectionView;

/**
 * The sample collection screen: the patient information panel (`patient`, `invoice`, `case`) and the grid
 * (`subjects`: the patient first, then donors in sequence order). A row can be ticked for collection when
 * `is_collectable`; `sample` is its latest sample, with `label_print_count` (0 = label never printed, so the
 * next print needs no reason). After a collect, `collected_sample_ids` lists the samples just drawn so their
 * labels can be printed straight away.
 *
 * @property-read SampleCollectionView $resource
 */
final class SampleCollectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $case = $this->resource->case;

        return [
            'case' => [
                'id' => $case->id,
                'case_no' => $case->case_no,
                'status' => $case->status->value,
                'care_type' => $case->care_type->value,
                'transfusion_reason' => $case->transfusion_reason,
                'reference_note' => $case->reference_note,
                'created_at' => $case->created_at?->toIso8601String(),
                'components' => CaseComponentResource::collection($case->components),
            ],
            'patient' => $this->resource->patient?->toArray(),
            'invoice' => $this->resource->invoice?->toArray(),
            'subjects' => array_map(fn (CollectionSubject $subject): array => [
                ...SubjectResource::make($subject->subject)->toArray($request),
                'is_collectable' => $subject->isCollectable,
                'sample' => $subject->sample === null ? null : [
                    'id' => $subject->sample->id,
                    'sample_no' => $subject->sample->sample_no,
                    'status' => $subject->sample->status->value,
                    'collected_at' => $subject->sample->collected_at->toIso8601String(),
                    'label_print_count' => $this->resource->labelPrintCounts[$subject->sample->id] ?? 0,
                ],
            ], $this->resource->subjects),
            'collected_sample_ids' => $this->resource->collectedSampleIds,
        ];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        if ($this->resource->collectedSampleIds !== []) {
            $response->setStatusCode(201);
        }
    }
}
