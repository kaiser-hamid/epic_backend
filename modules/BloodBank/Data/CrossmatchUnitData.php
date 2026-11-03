<?php

namespace Modules\BloodBank\Data;

use Modules\BloodBank\Enums\CrossmatchResult;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The crossmatch of one unit to issue: the patient sample against this unit, major and minor.
 */
#[MapName(SnakeCaseMapper::class)]
final class CrossmatchUnitData extends Data
{
    public function __construct(
        public int $unitId,
        public CrossmatchResult $majorResult,
        public CrossmatchResult $minorResult,
        #[Max(1000)]
        public ?string $remarks = null,
    ) {}

    public function isCompatible(): bool
    {
        return $this->majorResult === CrossmatchResult::Compatible && $this->minorResult === CrossmatchResult::Compatible;
    }
}
