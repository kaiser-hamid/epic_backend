<?php

namespace Modules\Shared\Billing\Data;

/**
 * A priced bill line in paisa: `amount` is gross (rate × quantity), `discountRateBp` the rate applied (null when the
 * bill has no policy), `netAmount` = amount − discountAmount.
 */
final readonly class PricedLine
{
    public function __construct(
        public int $testItemId,
        public int $quantity,
        public int $rate,
        public int $amount,
        public ?int $discountRateBp,
        public int $discountAmount,
        public int $netAmount,
    ) {}
}
