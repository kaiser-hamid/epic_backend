<?php

namespace Modules\BloodBank\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\BloodBank\Data\BagView;

/**
 * A donation (a row of the Collected tab) with its donor, case and patient, and its `units`: the component bags
 * filled at collection, whose unit nos are the numbers on the labels (a donation has none of its own). Ages are in
 * years.
 *
 * @property-read BagView $resource
 */
final class BagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $collection = $this->resource->collection;
        $caseDonor = $collection->caseDonor;
        $donor = $caseDonor->donor;
        $case = $caseDonor->patientCase;
        $patient = $this->resource->patient;

        return [
            'id' => $collection->id,
            'units' => DonationUnitResource::forCollection($collection, $request),
            'status' => $collection->status->value,
            'notes' => $collection->notes,
            'collected_at' => $collection->collected_at->toIso8601String(),
            'collected_by' => ['id' => $collection->collector->id, 'name' => $collection->collector->name],
            'donor' => [
                'case_donor_id' => $caseDonor->id,
                'sequence_no' => $caseDonor->sequence_no,
                'donor_no' => $donor->donor_no,
                'name' => $donor->name,
                'age' => $donor->currentAge(),
                'age_label' => $donor->ageLabel(),
                'gender' => $donor->gender?->value,
                'blood_group' => $donor->blood_group?->value,
            ],
            'case' => [
                'id' => $case->id,
                'case_no' => $case->case_no,
                'care_type' => $case->care_type->value,
            ],
            'patient' => $patient === null ? null : [
                'patient_no' => $patient->patientNo,
                'name' => $patient->name,
                'age' => $patient->ageYears,
                'age_label' => $patient->ageLabel(),
                'gender' => $patient->gender?->value,
                'blood_group' => $patient->bloodGroup?->value,
            ],
        ];
    }
}
