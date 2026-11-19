<?php

namespace Modules\BloodBank\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\BeforeOrEqual;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The "Collect" popup for one donor: the volume drawn into the bag (ml), optional notes, `collected_at` (defaults
 * to now; it may be back-dated, never future-dated), and one row per unit of the bag plan with its storage
 * location and optional volume. Rows of the plan that need no location may be left out.
 */
#[MapName(SnakeCaseMapper::class)]
final class CollectBloodData extends Data
{
    /**
     * @param  list<CollectedUnitData>  $units
     */
    public function __construct(
        #[Between(50, 600)]
        public int $volumeMl,
        #[Max(500)]
        public ?string $notes = null,
        #[BeforeOrEqual('now')]
        public ?CarbonImmutable $collectedAt = null,
        #[DataCollectionOf(CollectedUnitData::class), Max(6)]
        public array $units = [],
    ) {}
}
