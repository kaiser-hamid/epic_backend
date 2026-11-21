<?php

namespace Modules\BloodBank\Services;

use Modules\Shared\Numbering\Contracts\NumberGenerator;

/**
 * Unit numbers, printed as the unit label barcode, e.g. `BC2611020001`: the component's `unit_prefix`, `yyMMdd`
 * of collection, then one counter for that day shared by all branches, padded to four digits (it starts again at
 * 0001 every day). Blood is drawn straight into the component bags, so there is no main bag number: one donation
 * takes one counter value, and the units drawn together share its digits (`BC2611020001`, `BF2611020001`).
 */
final class UnitNumber
{
    public const string SEQUENCE = 'bb.donation';

    public function __construct(private readonly NumberGenerator $numbers) {}

    /**
     * The digits shared by the units of the next donation (`2611020001`).
     */
    public function nextDonation(): string
    {
        $day = now()->format('ymd');

        return $day.str_pad((string) $this->numbers->next(self::SEQUENCE, $day), 4, '0', STR_PAD_LEFT);
    }

    public function forUnit(string $donationDigits, string $unitPrefix): string
    {
        return $unitPrefix.$donationDigits;
    }
}
