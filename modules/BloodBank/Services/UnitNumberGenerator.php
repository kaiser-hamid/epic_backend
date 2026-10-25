<?php

namespace Modules\BloodBank\Services;

/**
 * Builds the barcode number of a blood bag so each physical bag carries exactly one barcode.
 *
 * The source (whole-blood) unit reuses its collection's bag number; a component appends its
 * component code, plus a split number when one component is divided into several bags.
 */
final class UnitNumberGenerator
{
    public function forUnit(string $bagNo, ?string $componentCode = null, ?int $splitNo = null): string
    {
        if ($componentCode === null) {
            return $bagNo;
        }

        $unitNo = "{$bagNo}-{$componentCode}";

        return $splitNo === null ? $unitNo : "{$unitNo}-{$splitNo}";
    }
}
