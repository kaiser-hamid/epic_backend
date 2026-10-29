<?php

namespace Modules\BloodBank\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\BloodBank\Data\DonorView;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\Collection;

/**
 * The donor fields of DonorResource plus `donations`: one entry per case the donor was registered for, newest
 * first, with the patient it was for and the bags collected (empty when deferred or not yet bled).
 *
 * @property-read DonorView $resource
 */
final class DonorDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $donor = $this->resource->donor;

        return [
            ...DonorResource::make($donor)->toArray($request),
            'donations' => $donor->caseDonors->map(fn (CaseDonor $caseDonor): array => $this->donation($caseDonor))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function donation(CaseDonor $caseDonor): array
    {
        $case = $caseDonor->patientCase;

        return [
            'case_donor_id' => $caseDonor->id,
            'sequence_no' => $caseDonor->sequence_no,
            'status' => $caseDonor->status->value,
            'deferral_reason' => $caseDonor->deferral_reason,
            'assessed_at' => $caseDonor->assessed_at?->toIso8601String(),
            'registered_at' => $caseDonor->created_at?->toIso8601String(),
            'case' => [
                'id' => $case->id,
                'case_no' => $case->case_no,
                'branch_id' => $case->branch_id,
                'status' => $case->status->value,
                'care_type' => $case->care_type->value,
                'transfusion_reason' => $case->transfusion_reason,
            ],
            'patient' => ($this->resource->patients[$case->patient_master_id] ?? null)?->toArray(),
            'collections' => $caseDonor->collections->map(fn (Collection $collection): array => [
                'id' => $collection->id,
                'bag_no' => $collection->bag_no,
                'volume_ml' => $collection->volume_ml,
                'status' => $collection->status->value,
                'collected_at' => $collection->collected_at?->toIso8601String(),
            ])->all(),
        ];
    }
}
