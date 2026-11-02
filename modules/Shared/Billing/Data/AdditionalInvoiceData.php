<?php

namespace Modules\Shared\Billing\Data;

/**
 * A follow-up bill for the patient of an existing bill (`sourceBillingId`), copying its referral, for one
 * catalog item named by code. `invoiceNo` is the number issued outside this system (the vendor's), not generated.
 */
final readonly class AdditionalInvoiceData
{
    public function __construct(
        public int $sourceBillingId,
        public string $invoiceNo,
        public string $testItemCode,
        public int $quantity = 1,
    ) {}
}
