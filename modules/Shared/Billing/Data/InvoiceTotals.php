<?php

namespace Modules\Shared\Billing\Data;

/**
 * A bill's money in paisa: `subtotalAmount` − `discountAmount` + `roundingAmount` = `amount` (the payable).
 * `roundingAmount` is signed: dropping paisa lowers it, rounding up to the next 10 taka raises it.
 */
final readonly class InvoiceTotals
{
    /**
     * @param  list<PricedLine>  $lines
     */
    public function __construct(
        public array $lines,
        public int $subtotalAmount,
        public int $discountAmount,
        public int $roundingAmount,
        public int $amount,
    ) {}
}
