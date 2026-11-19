<?php

namespace Modules\BloodBank\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One row of the "Collect" popup: a unit of the bag plan (by component type), the shelf it is stored on (required
 * for a stock unit, and for a patient unit of a case that keeps its units in storage) and, optionally, its volume.
 */
#[MapName(SnakeCaseMapper::class)]
final class CollectedUnitData extends Data
{
    public function __construct(
        public int $componentTypeId,
        public ?int $storageLocationId = null,
        #[Between(1, 600)]
        public ?int $volumeMl = null,
    ) {}
}
