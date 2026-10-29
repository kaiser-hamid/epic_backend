<?php

namespace Modules\Shared\Patient\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shared\Patient\Models\PatientMaster;

/**
 * @mixin PatientMaster
 */
final class PatientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $age = $this->currentAge();

        return [
            'id' => $this->id,
            'patient_no' => $this->patient_no,
            'title' => $this->title?->value,
            'name' => $this->name,
            'address' => $this->address,
            'age_years' => $age['years'],
            'age_months' => $age['months'],
            'age_days' => $age['days'],
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'gender' => $this->gender?->value,
            'marital_status' => $this->marital_status?->value,
            'blood_group' => $this->blood_group?->value,
            'phone' => $this->phone,
            'national_id' => $this->national_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
