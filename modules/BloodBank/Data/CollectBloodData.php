<?php

namespace Modules\BloodBank\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\BeforeOrEqual;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The "Collect" dialog for one donor: the volume drawn into the bag (ml), optional notes, and `collected_at`
 * (defaults to now; it may be back-dated, never future-dated).
 */
#[MapName(SnakeCaseMapper::class)]
final class CollectBloodData extends Data
{
    public function __construct(
        #[Between(50, 600)]
        public int $volumeMl,
        #[Max(500)]
        public ?string $notes = null,
        #[BeforeOrEqual('now')]
        public ?CarbonImmutable $collectedAt = null,
    ) {}
}
