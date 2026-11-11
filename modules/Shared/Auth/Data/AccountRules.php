<?php

namespace Modules\Shared\Auth\Data;

/**
 * Validation shared by the account forms: Bangladeshi mobile numbers (`01` + 9 digits) and every
 * password (new user, admin reset, own change): letters and/or digits only, no special characters.
 */
final class AccountRules
{
    public const PHONE_PATTERN = '/^01\d{9}$/';

    public const PASSWORD_PATTERN = '/^[A-Za-z0-9]+$/';

    public const PASSWORD_MIN = 10;

    public const PASSWORD_MAX = 64;

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'phone.regex' => 'Enter an 11-digit mobile number starting with 01.',
            'password.regex' => 'Use letters and numbers only, without special characters.',
        ];
    }
}
