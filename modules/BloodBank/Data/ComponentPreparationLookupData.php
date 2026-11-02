<?php

namespace Modules\BloodBank\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The component preparation screen's scan: `?bag_no=` (the bag label barcode).
 */
#[MapName(SnakeCaseMapper::class)]
final class ComponentPreparationLookupData extends Data
{
    public function __construct(
        #[Max(30)]
        public string $bagNo,
    ) {}
}
