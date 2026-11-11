<?php

namespace Modules\Shared\Auth\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * A temporary password set by an admin: letters and/or digits only. The user must change it on next login.
 */
#[MapName(SnakeCaseMapper::class)]
final class ResetPasswordData extends Data
{
    public function __construct(
        #[Min(AccountRules::PASSWORD_MIN), Max(AccountRules::PASSWORD_MAX), Regex(AccountRules::PASSWORD_PATTERN)]
        public string $password,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return AccountRules::messages();
    }
}
