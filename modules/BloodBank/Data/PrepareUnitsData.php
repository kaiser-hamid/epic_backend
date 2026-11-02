<?php

namespace Modules\BloodBank\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\BeforeOrEqual;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The "Prepare" form for one bag: either one whole-blood unit, or the components it is separated into.
 * `prepared_at` defaults to now; it may be back-dated to the collection time, never future-dated.
 */
#[MapName(SnakeCaseMapper::class)]
final class PrepareUnitsData extends Data
{
    /**
     * @param  list<PreparedUnitData>  $units
     */
    public function __construct(
        #[DataCollectionOf(PreparedUnitData::class), Min(1), Max(6)]
        public array $units,
        #[BeforeOrEqual('now')]
        public ?CarbonImmutable $preparedAt = null,
    ) {}
}
