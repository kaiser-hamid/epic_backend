<?php

namespace Modules\Shared\Support\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * `?phone=` of a lookup that pre-fills a form with a returning person. Matched exactly, as typed.
 */
#[MapName(SnakeCaseMapper::class)]
final class PhoneLookupData extends Data
{
    public function __construct(
        #[Max(20)]
        public string $phone,
    ) {}
}
