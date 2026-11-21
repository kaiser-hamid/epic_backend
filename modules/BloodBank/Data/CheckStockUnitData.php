<?php

namespace Modules\BloodBank\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The `unit_no` of a stock bag scanned on the sample collection screen, checked before it is listed for Collect.
 */
#[MapName(SnakeCaseMapper::class)]
final class CheckStockUnitData extends Data
{
    public function __construct(
        #[Max(50)]
        public string $unitNo,
    ) {}
}
