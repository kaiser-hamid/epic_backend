<?php

namespace Modules\Shared\Auth\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
final class LoginBranchesData extends Data
{
    public function __construct(
        #[Max(255)]
        public string $username,
    ) {}
}
