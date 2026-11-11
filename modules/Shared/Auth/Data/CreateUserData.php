<?php

namespace Modules\Shared\Auth\Data;

use Modules\Shared\Auth\Enums\Designation;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\AlphaDash;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * A new staff account. The employee id is generated; the initial password must be changed on first login.
 */
#[MapName(SnakeCaseMapper::class)]
final class CreateUserData extends Data
{
    public function __construct(
        #[Max(255)]
        public string $name,
        #[Max(50), AlphaDash, Unique('users', 'username')]
        public string $username,
        #[Min(AccountRules::PASSWORD_MIN), Max(AccountRules::PASSWORD_MAX), Regex(AccountRules::PASSWORD_PATTERN)]
        public string $password,
        public ?Designation $designation = null,
        #[Regex(AccountRules::PHONE_PATTERN)]
        public ?string $phone = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return AccountRules::messages();
    }
}
