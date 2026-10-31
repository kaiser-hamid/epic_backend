<?php

namespace Modules\BloodBank\Services;

use Modules\Shared\Numbering\Contracts\NumberGenerator;

/**
 * Sample numbers, printed as the label barcode, e.g. `S2610310001`: `S`, `yyMMdd` of collection, then one
 * counter for that day shared by all branches and by patient and donor samples, padded to four digits. It
 * starts again at 0001 every day.
 */
final class SampleNumber
{
    public const SEQUENCE = 'bb.sample';

    public function __construct(private readonly NumberGenerator $numbers) {}

    public function next(): string
    {
        $day = now()->format('ymd');

        return 'S'.$day.str_pad((string) $this->numbers->next(self::SEQUENCE, $day), 4, '0', STR_PAD_LEFT);
    }
}
