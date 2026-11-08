<?php

namespace Modules\BloodBank\Services;

/**
 * Builds the barcode number of a blood bag so each physical bag carries exactly one barcode.
 *
 * The number is the collection's bag number with the component type's prefix in place of its leading "B"
 * (bag B2611020003 → BT2611020003 for platelets), plus a split number when one component is divided into several bags.
 */
final class UnitNumberGenerator
{
    public function forUnit(string $bagNo, string $unitPrefix, ?int $splitNo = null): string
    {
        $unitNo = $unitPrefix.substr($bagNo, 1);

        return $splitNo === null ? $unitNo : "{$unitNo}-{$splitNo}";
    }
}
