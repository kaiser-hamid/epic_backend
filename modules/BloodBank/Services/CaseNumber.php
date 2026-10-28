<?php

namespace Modules\BloodBank\Services;

use Modules\Shared\Numbering\Contracts\NumberGenerator;

/**
 * Case numbers, e.g. `BC2610000001`: `BC`, `yyMM` of registration, then one counter for that month shared
 * by all branches, padded to six digits. It starts again at 000001 every month.
 */
final class CaseNumber
{
    public const SEQUENCE = 'bb.case';

    public function __construct(private readonly NumberGenerator $numbers) {}

    public function next(): string
    {
        $month = now()->format('ym');

        return 'BC'.$month.str_pad((string) $this->numbers->next(self::SEQUENCE, $month), 6, '0', STR_PAD_LEFT);
    }
}
