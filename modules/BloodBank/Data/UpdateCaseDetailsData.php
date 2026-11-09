<?php

namespace Modules\BloodBank\Data;

use Modules\BloodBank\Enums\CareType;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

/**
 * The case-only details staff edit on an opened case; only the fields sent are changed. "Care of" can be
 * corrected here after the bill; cause of transfusion and the note are entered here (null clears them).
 */
#[MapName(SnakeCaseMapper::class)]
final class UpdateCaseDetailsData extends Data
{
    public function __construct(
        public Optional|CareType $careType,
        #[Max(1000)]
        public Optional|string|null $transfusionReason,
        #[Max(1000)]
        public Optional|string|null $referenceNote,
    ) {}
}
