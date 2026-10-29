<?php

namespace Modules\Shared\Support\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * `?search=` of a type-ahead lookup: part of a code or a name.
 */
#[MapName(SnakeCaseMapper::class)]
final class SearchLookupData extends Data
{
    public function __construct(
        #[Max(100)]
        public string $search,
    ) {}
}
