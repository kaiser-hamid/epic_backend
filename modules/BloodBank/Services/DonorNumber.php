<?php

namespace Modules\BloodBank\Services;

use Modules\Shared\Numbering\Contracts\NumberGenerator;

/**
 * Donor numbers, e.g. `D2610000001`: `D`, `yyMM` of registration, then one counter for that month shared
 * by all branches (donors are not branch-owned), padded to six digits. It starts again at 000001 every month.
 */
final class DonorNumber
{
    public const SEQUENCE = 'bb.donor';

    public function __construct(private readonly NumberGenerator $numbers) {}

    public function next(): string
    {
        $month = now()->format('ym');

        return 'D'.$month.str_pad((string) $this->numbers->next(self::SEQUENCE, $month), 6, '0', STR_PAD_LEFT);
    }
}
