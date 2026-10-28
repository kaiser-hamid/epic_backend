<?php

namespace Modules\BloodBank\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\BloodBank\Models\CaseDonor;

/**
 * @mixin CaseDonor
 */
final class CaseDonorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sequence_no' => $this->sequence_no,
            'status' => $this->status->value,
            'billing_id' => $this->billing_id,
            'deferral_reason' => $this->deferral_reason,
            'assessed_at' => $this->assessed_at?->toIso8601String(),
            'donor' => DonorResource::make($this->whenLoaded('donor')),
        ];
    }
}
