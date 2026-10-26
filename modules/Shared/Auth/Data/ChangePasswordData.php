<?php

namespace Modules\Shared\Auth\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Confirmed;
use Spatie\LaravelData\Attributes\Validation\CurrentPassword;
use Spatie\LaravelData\Attributes\Validation\Different;
use Spatie\LaravelData\Attributes\Validation\Password;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
final class ChangePasswordData extends Data
{
    public function __construct(
        #[CurrentPassword]
        public string $currentPassword,
        #[Password(default: true), Confirmed, Different('current_password')]
        public string $password,
    ) {}
}
