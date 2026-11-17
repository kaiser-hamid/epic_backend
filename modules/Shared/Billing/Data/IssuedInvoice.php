<?php

namespace Modules\Shared\Billing\Data;

use Carbon\CarbonImmutable;

/**
 * What another module needs from a just-saved bill: the ids to store, the numbers to show and the billed lines of
 * that module's category only (a bill may mix categories). `amount` is still the whole bill's payable.
 * Never a Shared model.
 */
final readonly class IssuedInvoice
{
    /**
     * @param  list<IssuedInvoiceLine>  $lines
     */
    public function __construct(
        public int $billingId,
        public string $invoiceNo,
        public int $branchId,
        public int $patientId,
        public string $patientNo,
        public int $amount,
        public CarbonImmutable $billedAt,
        public array $lines,
    ) {}
}
