<?php

namespace Modules\BloodBank\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\BloodBank\Models\PatientCase;

/**
 * The case block of a work screen (sample collection, donor examination), with `components` loaded.
 *
 * @mixin PatientCase
 */
final class CaseSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'case_no' => $this->case_no,
            'status' => $this->status->value,
            'care_type' => $this->care_type->value,
            'transfusion_reason' => $this->transfusion_reason,
            'reference_note' => $this->reference_note,
            'created_at' => $this->created_at?->toIso8601String(),
            'components' => CaseComponentResource::collection($this->components),
        ];
    }
}
