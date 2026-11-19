<?php

namespace Modules\BloodBank\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * "Close case" on the case details page: `reason` is optional when nothing billed is outstanding, required
 * otherwise (`CLOSE_REASON_REQUIRED`).
 */
#[MapName(SnakeCaseMapper::class)]
final class CloseCaseData extends Data
{
    public function __construct(
        #[Max(1000)]
        public ?string $reason = null,
    ) {}
}
