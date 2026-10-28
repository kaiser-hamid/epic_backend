<?php

namespace Modules\Shared\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Patient\Http\Resources\PatientResource;

/**
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
            'status' => $this->status->value,
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
