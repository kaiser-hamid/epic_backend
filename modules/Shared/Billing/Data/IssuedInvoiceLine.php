<?php

namespace Modules\Shared\Billing\Data;

/**
 * One billed item of an issued invoice, for the module that opens its own record from the bill.
 */
final readonly class IssuedInvoiceLine
{
    public function __construct(
        public int $testItemId,
        public int $quantity,
    ) {}
}
