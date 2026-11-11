<?php

namespace Modules\Shared\Auth\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Confirmed;
use Spatie\LaravelData\Attributes\Validation\CurrentPassword;
use Spatie\LaravelData\Attributes\Validation\Different;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The signed-in user replaces their own password. Same rule as admin-set passwords: letters and/or digits.
 */
#[MapName(SnakeCaseMapper::class)]
final class ChangePasswordData extends Data
{
    public function __construct(
        #[CurrentPassword]
        public string $currentPassword,
        #[Min(AccountRules::PASSWORD_MIN), Max(AccountRules::PASSWORD_MAX), Regex(AccountRules::PASSWORD_PATTERN), Confirmed, Different('current_password')]
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
