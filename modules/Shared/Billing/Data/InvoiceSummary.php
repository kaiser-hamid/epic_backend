<?php

namespace Modules\Shared\Billing\Data;

use Carbon\CarbonImmutable;
use Modules\Shared\Billing\Enums\BillingStatus;
use Modules\Shared\Billing\Models\Billing;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The invoice details other modules show next to their own records. `referred_by` is the referring
 * doctor's name (the Blood Bank register's "Name of consultant"); null for a self-referral.
 */
#[MapName(SnakeCaseMapper::class)]
final class InvoiceSummary extends Data
{
    public function __construct(
        public int $id,
        public string $invoiceNo,
        public int $amount,
        public BillingStatus $status,
        public CarbonImmutable $billedAt,
        public bool $isSelfReferral,
        public ?string $referredBy,
    ) {}

    public static function fromModel(Billing $billing): self
    {
        return new self(
            id: $billing->id,
            invoiceNo: $billing->invoice_no,
            amount: $billing->amount,
            status: $billing->status,
            billedAt: $billing->billed_at->toImmutable(),
            isSelfReferral: (bool) $billing->referral?->is_self_referral,
            referredBy: $billing->referral?->doctor?->name,
        );
    }
}
