<?php

namespace Modules\Shared\Patient\Services;

use Modules\Shared\Numbering\Contracts\NumberGenerator;

/**
 * Patient numbers, e.g. `P2610280001`: `P`, `yyMMdd` of registration, then one counter for that day shared by
 * all branches (patients are not branch-owned), padded to four digits. It starts again at 0001 every day.
 */
final class PatientNumber
{
    public const SEQUENCE = 'sh.patient';

    public function __construct(private readonly NumberGenerator $numbers) {}

    public function next(): string
    {
        $day = now()->format('ymd');

        return 'P'.$day.str_pad((string) $this->numbers->next(self::SEQUENCE, $day), 4, '0', STR_PAD_LEFT);
    }
}
