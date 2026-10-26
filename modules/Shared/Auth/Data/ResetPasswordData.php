<?php

namespace Modules\Shared\Auth\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Password;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * A temporary password set by an admin. The user must change it on next login.
 */
#[MapName(SnakeCaseMapper::class)]
final class ResetPasswordData extends Data
{
    public function __construct(
        #[Password(default: true)]
        public string $password,
    ) {}
}
