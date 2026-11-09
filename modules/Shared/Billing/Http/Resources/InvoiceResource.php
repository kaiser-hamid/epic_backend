<?php

namespace Modules\Shared\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Patient\Http\Resources\PatientResource;

/**
 * A bill. Money is integer paisa: `subtotal_amount` − `discount_amount` + `rounding_amount` = `amount` (payable).
 * `discount` is the policy snapshot the bill applied, or null.
 *
 * @mixin Billing
 */
final class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_no' => $this->invoice_no,
            'branch_id' => $this->branch_id,
            'category' => $this->category->value,
            'status' => $this->status->value,
            'patient_type' => $this->whenLoaded('patientType', fn () => [
                'id' => $this->patientType->id,
                'code' => $this->patientType->code,
                'name' => $this->patientType->name,
            ]),
            'discount' => $this->whenLoaded('discounts', fn () => $this->discounts->isEmpty() ? null : [
                'discount_policy_id' => $this->discounts->first()->discount_policy_id,
                'name' => $this->discounts->first()->name,
                'discount_percent' => $this->discounts->first()->rate_bp / 100,
                'amount' => $this->discounts->first()->amount,
            ]),
            'subtotal_amount' => $this->subtotal_amount,
            'discount_amount' => $this->discount_amount,
            'rounding_amount' => $this->rounding_amount,
            'amount' => $this->amount,
            'billed_at' => $this->billed_at->toIso8601String(),
            'patient' => PatientResource::make($this->whenLoaded('patient')),
            'referral' => InvoiceReferralResource::make($this->whenLoaded('referral')),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'created_by' => $this->whenLoaded('creator', fn () => ['id' => $this->creator->id, 'name' => $this->creator->name]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
