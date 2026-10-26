<?php

namespace Modules\Shared\Auth\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\AlphaDash;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Password;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * A new staff account. The initial password must be changed on first login.
 */
#[MapName(SnakeCaseMapper::class)]
final class CreateUserData extends Data
{
    public function __construct(
        #[Max(50), Unique('users', 'employee_id')]
        public string $employeeId,
        #[Max(255)]
        public string $name,
        #[Max(50), AlphaDash, Unique('users', 'username')]
        public string $username,
        #[Password(default: true)]
        public string $password,
        #[Max(255)]
        public ?string $designation = null,
        #[Max(20)]
        public ?string $phone = null,
    ) {}
}
