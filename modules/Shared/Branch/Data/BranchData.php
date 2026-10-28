<?php

namespace Modules\Shared\Branch\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\References\RouteParameterReference;

#[MapName(SnakeCaseMapper::class)]
final class BranchData extends Data
{
    public function __construct(
        #[Regex('/^[A-Z]{2}$/'), Unique('sh_branches', 'code', ignore: new RouteParameterReference('branch', 'id', nullable: true))]
        public string $code,
        #[Max(255)]
        public string $name,
        public bool $isActive = true,
        #[Max(1000)]
        public ?string $address = null,
        #[Max(20)]
        public ?string $phone = null,
    ) {}
}
