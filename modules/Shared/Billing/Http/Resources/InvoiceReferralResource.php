<?php

namespace Modules\Shared\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shared\Billing\Models\BillingReferral;
use Modules\Shared\Doctor\Models\Doctor;

/**
 * @mixin BillingReferral
 */
final class InvoiceReferralResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'is_self_referral' => $this->is_self_referral,
            'doctor' => $this->whenLoaded('doctor', fn () => $this->doctor($this->doctor)),
            'second_doctor' => $this->whenLoaded('secondDoctor', fn () => $this->doctor($this->secondDoctor)),
            'remark' => $this->remark,
            'referred_by_staff' => $this->whenLoaded('referredByStaff', fn () => $this->referredByStaff === null
                ? null
                : ['id' => $this->referredByStaff->id, 'name' => $this->referredByStaff->name]),
        ];
    }

    /**
     * @return array{id: int, code: string, name: string, degree: ?string}|null
     */
    private function doctor(?Doctor $doctor): ?array
    {
        return $doctor?->only(['id', 'code', 'name', 'degree']);
    }
}
