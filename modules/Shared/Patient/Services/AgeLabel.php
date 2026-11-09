<?php

namespace Modules\Shared\Patient\Services;

/**
 * The one way an age is shown anywhere (screens, labels, reports, bills): only its largest unit. Years when there
 * are any ("78Y"), else months ("5M"), else days ("22D"); a known age of nothing at all is "0D".
 */
final class AgeLabel
{
    public static function format(?int $years, ?int $months = null, ?int $days = null): ?string
    {
        return match (true) {
            ($years ?? 0) > 0 => $years.'Y',
            ($months ?? 0) > 0 => $months.'M',
            ($days ?? 0) > 0 => $days.'D',
            $years !== null || $months !== null || $days !== null => '0D',
            default => null,
        };
    }
}
