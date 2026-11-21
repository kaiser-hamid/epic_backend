<?php

namespace Modules\BloodBank\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\BloodBank\Data\SubjectView;
use Modules\BloodBank\Enums\CaseDonorSource;
use Modules\BloodBank\Enums\SampleSubject;
use Modules\BloodBank\Models\CaseDonor;

/**
 * Who a sample is drawn from, in one shape for the patient and donors: `number` is the patient no or donor no,
 * `age` is in years and `age_label` is the age as shown everywhere ("78Y", "5M", "22D"). The `case_donor_*` and `sequence_no` fields are null for the patient.
 * A donor row's `source` is `donor` or `stock`; a stock row (a stock unit standing in for a donor, the person being
 * the unit's original donor) carries its unit in `stock_unit`, null otherwise.
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
                'source' => ($caseDonor?->source ?? CaseDonorSource::Donor)->value,
                'stock_unit' => $this->stockUnit($caseDonor, $request),
                'sequence_no' => $caseDonor?->sequence_no,
                'number' => $donor?->donor_no,
                'name' => $donor?->name,
                'age' => $donor?->currentAge(),
                'age_label' => $donor?->ageLabel(),
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
            'source' => null,
            'stock_unit' => null,
            'sequence_no' => null,
            'number' => $patient?->patientNo,
            'name' => $patient?->name,
            'age' => $patient?->ageYears,
            'age_label' => $patient?->ageLabel(),
            'gender' => $patient?->gender?->value,
            'blood_group' => $patient?->bloodGroup?->value,
            'phone' => $patient?->phone,
        ];
    }

    /**
     * The unit of a stock row, null for a real donor. Loaded here when the screen did not: stock rows are few.
     *
     * @return array<string, mixed>|null
     */
    private function stockUnit(?CaseDonor $caseDonor, Request $request): ?array
    {
        if ($caseDonor === null || ! $caseDonor->isStock()) {
            return null;
        }

        $unit = $caseDonor->loadMissing(['unit.componentType', 'unit.storageLocation'])->unit;

        return $unit === null ? null : StockUnitResource::make($unit)->resolve($request);
    }
}
