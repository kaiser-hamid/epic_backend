<?php

namespace Modules\BloodBank\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One unit made from the bag: its component, volume, and whether it is reserved for this case's patient
 * (`for_case`) or goes to stock. A stock unit needs a storage location; a unit for the patient may have one.
 */
#[MapName(SnakeCaseMapper::class)]
final class PreparedUnitData extends Data
{
    public function __construct(
        public int $componentTypeId,
        public ?int $storageLocationId = null,
        #[Between(1, 600)]
        public ?int $volumeMl = null,
        public bool $forCase = false,
    ) {}
}
