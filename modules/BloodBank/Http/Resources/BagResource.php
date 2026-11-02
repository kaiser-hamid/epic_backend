<?php

namespace Modules\BloodBank\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\BloodBank\Data\BagView;

/**
 * A blood bag with its donor, case and patient; also the content of a printed bag label. The label barcodes are
 * `bag_no`, `donor.donor_no` and `case.case_no`. Ages are in years. `label_print` is present only in the
 * response to a label print.
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
        $labelPrint = $this->resource->labelPrint;

        return [
            'id' => $collection->id,
            'bag_no' => $collection->bag_no,
            'status' => $collection->status->value,
            'volume_ml' => $collection->volume_ml,
            'notes' => $collection->notes,
            'collected_at' => $collection->collected_at->toIso8601String(),
            'collected_by' => ['id' => $collection->collector->id, 'name' => $collection->collector->name],
            'donor' => [
                'case_donor_id' => $caseDonor->id,
                'sequence_no' => $caseDonor->sequence_no,
                'donor_no' => $donor->donor_no,
                'name' => $donor->name,
                'age' => $donor->currentAge(),
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
                'gender' => $patient->gender?->value,
                'blood_group' => $patient->bloodGroup?->value,
            ],
            'label_print' => $this->when($labelPrint !== null, fn () => [
                'is_reprint' => $labelPrint->is_reprint,
                'reprint_reason' => $labelPrint->reprint_reason,
                'printed_at' => $labelPrint->printed_at->toIso8601String(),
            ]),
        ];
    }
}
