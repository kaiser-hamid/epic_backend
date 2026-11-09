<?php

namespace Modules\Shared\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shared\Billing\Models\DiscountPolicy;
use Modules\Shared\Billing\Models\PatientType;

/**
 * @mixin PatientType
 */
final class PatientTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'discount_policies' => $this->whenLoaded('discountPolicies', fn () => $this->discountPolicies
                ->map(fn (DiscountPolicy $policy): array => [
                    'id' => $policy->id,
                    'code' => $policy->code,
                    'name' => $policy->name,
                    'type' => $policy->type->value,
                    'discount_percent' => $policy->rate_bp / 100,
                ])->values()->all()),
        ];
    }
}
