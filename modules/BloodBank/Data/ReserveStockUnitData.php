<?php

namespace Modules\BloodBank\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * "Print patient label" for a stock row on the blood collection screen. The unit stays in its fridge unless
 * `storage_location_id` moves it (an active location of the case's branch).
 */
#[MapName(SnakeCaseMapper::class)]
final class ReserveStockUnitData extends Data
{
    public function __construct(
        public ?int $storageLocationId = null,
    ) {}
}
