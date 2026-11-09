<?php

namespace Modules\Shared\Billing\Data;

/**
 * One line to price: the catalog rate snapshot and the item's own discount override (basis points; null follows
 * the bill's policy, 0 is never discounted).
 */
final readonly class BillLineInput
{
    public function __construct(
        public int $testItemId,
        public int $quantity,
        public int $rate,
        public ?int $itemDiscountRateBp = null,
    ) {}
}
