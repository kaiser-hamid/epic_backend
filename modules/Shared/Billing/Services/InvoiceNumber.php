<?php

namespace Modules\Shared\Billing\Services;

use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Numbering\Contracts\NumberGenerator;

/**
 * Invoice numbers in the vendor format, e.g. `NE2610280001`: the two-letter branch code, `yyMMdd` of billing,
 * then the branch's counter for that day padded to four digits. Each branch starts again at 0001 every day.
 */
final class InvoiceNumber
{
    public const SEQUENCE = 'sh.invoice';

    public function __construct(private readonly NumberGenerator $numbers) {}

    public function next(Branch $branch): string
    {
        $day = now()->format('ymd');
        $value = $this->numbers->next(self::SEQUENCE, "{$branch->id}:{$day}");

        return $branch->code.$day.str_pad((string) $value, 4, '0', STR_PAD_LEFT);
    }
}
