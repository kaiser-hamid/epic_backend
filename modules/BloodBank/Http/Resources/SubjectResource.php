<?php

namespace Modules\BloodBank\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\BloodBank\Data\SubjectView;
use Modules\BloodBank\Enums\SampleSubject;

/**
 * Who a sample is drawn from, in one shape for the patient and donors: `number` is the patient no or donor no,
 * `age` is in years. The `case_donor_*` and `sequence_no` fields are null for the patient.
 *
 * @property-read SubjectView $resource
 */
final class SubjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $caseDonor = $this->resource->caseDonor;

        if ($this->resource->type === SampleSubject::Donor) {
            $donor = $caseDonor?->donor;

            return [
                'subject_type' => SampleSubject::Donor->value,
                'case_donor_id' => $caseDonor?->id,
                'case_donor_status' => $caseDonor?->status->value,
                'sequence_no' => $caseDonor?->sequence_no,
                'number' => $donor?->donor_no,
                'name' => $donor?->name,
                'age' => $donor?->currentAge(),
                'gender' => $donor?->gender?->value,
                'blood_group' => $donor?->blood_group?->value,
                'phone' => $donor?->phone,
            ];
        }

        $patient = $this->resource->patient;

        return [
            'subject_type' => SampleSubject::Patient->value,
            'case_donor_id' => null,
            'case_donor_status' => null,
            'sequence_no' => null,
            'number' => $patient?->patientNo,
            'name' => $patient?->name,
            'age' => $patient?->ageYears,
            'gender' => $patient?->gender?->value,
            'blood_group' => $patient?->bloodGroup?->value,
            'phone' => $patient?->phone,
        ];
    }
}
