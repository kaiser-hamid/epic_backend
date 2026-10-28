<?php

namespace Modules\BloodBank\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\BloodBank\Models\Donor;

/**
 * @mixin Donor
 */
final class DonorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'donor_no' => $this->donor_no,
            'name' => $this->name,
            'age' => $this->age,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'gender' => $this->gender?->value,
            'blood_group' => $this->blood_group?->value,
            'phone' => $this->phone,
            'address' => $this->address,
            'national_id' => $this->national_id,
            'is_regular_volunteer' => $this->is_regular_volunteer,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
