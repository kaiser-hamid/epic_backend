<?php

namespace Modules\Shared\Billing\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Print a bill. `reprint_reason` is required once the bill has been printed before.
 */
#[MapName(SnakeCaseMapper::class)]
final class PrintInvoiceData extends Data
{
    public function __construct(
        #[Max(500)]
        public ?string $reprintReason = null,
    ) {}
}
