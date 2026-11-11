<?php

namespace Modules\Shared\Auth\Services;

use Modules\Shared\Numbering\Contracts\NumberGenerator;

/**
 * Employee ids like `E2611001`: `E`, `yyMM` of account creation, then that month's counter padded to three
 * digits. The counter starts again at 001 every month and is shared by all branches.
 */
final class EmployeeId
{
    public const SEQUENCE = 'sh.employee';

    public function __construct(private readonly NumberGenerator $numbers) {}

    public function next(): string
    {
        $month = now()->format('ym');
        $value = $this->numbers->next(self::SEQUENCE, $month);

        return 'E'.$month.str_pad((string) $value, 3, '0', STR_PAD_LEFT);
    }
}
