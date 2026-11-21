<?php

namespace Modules\BloodBank\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One unit to issue, with the remarks of its crossmatch. The results are not sent: the unit is recorded
 * compatible on the sides its component's `CrossmatchScope` covers.
 */
#[MapName(SnakeCaseMapper::class)]
final class CrossmatchUnitData extends Data
{
    public function __construct(
        public int $unitId,
        #[Max(1000)]
        public ?string $remarks = null,
    ) {}
}
