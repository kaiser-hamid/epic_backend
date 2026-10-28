<?php

namespace Modules\BloodBank\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\BloodBank\Data\CaseView;

/**
 * @property-read CaseView $resource
 */
final class CaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $case = $this->resource->case;

        return [
            'id' => $case->id,
            'case_no' => $case->case_no,
            'branch_id' => $case->branch_id,
            'status' => $case->status->value,
            'care_type' => $case->care_type->value,
            'transfusion_reason' => $case->transfusion_reason,
            'reference_note' => $case->reference_note,
            'supplied_at' => $case->supplied_at?->toIso8601String(),
            'patient' => $this->resource->patient?->toArray(),
            'invoice' => $this->resource->invoice?->toArray(),
            'components' => $this->when($case->relationLoaded('components'), fn () => CaseComponentResource::collection($case->components)),
            'donors' => $this->when($case->relationLoaded('caseDonors'), fn () => CaseDonorResource::collection($case->caseDonors)),
            'donors_count' => $case->relationLoaded('caseDonors') ? $case->caseDonors->count() : $case->case_donors_count,
            'created_at' => $case->created_at?->toIso8601String(),
        ];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        if ($this->resource->case->wasRecentlyCreated) {
            $response->setStatusCode(201);
        }
    }
}
