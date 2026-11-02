<?php

namespace Modules\BloodBank\Services;

use Modules\Shared\Numbering\Contracts\NumberGenerator;

/**
 * Bag numbers, printed as the bag label barcode, e.g. `B2611020001`: `B`, `yyMMdd` of collection, then one
 * counter for that day shared by all branches, padded to four digits. It starts again at 0001 every day. The bag
 * no is also the base of the unit numbers made from the bag (see UnitNumberGenerator).
 */
final class BagNumber
{
    public const SEQUENCE = 'bb.bag';

    public function __construct(private readonly NumberGenerator $numbers) {}

    public function next(): string
    {
        $day = now()->format('ymd');

        return 'B'.$day.str_pad((string) $this->numbers->next(self::SEQUENCE, $day), 4, '0', STR_PAD_LEFT);
    }
}
