<?php

namespace Modules\Shared\Billing\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * What another module needs to keep after billing: the ids to store and the numbers to show.
 */
#[MapName(SnakeCaseMapper::class)]
final class IssuedInvoice extends Data
{
    public function __construct(
        public int $billingId,
        public string $invoiceNo,
        public int $patientId,
        public string $patientNo,
        public int $amount,
        public CarbonImmutable $billedAt,
    ) {}
}
