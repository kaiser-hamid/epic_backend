<?php

namespace Modules\Shared\Auth\Data;

use Modules\Shared\Auth\Enums\Designation;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

/**
 * Profile fields only. The employee id and username never change; status, password, lock and permissions
 * each have their own action.
 */
#[MapName(SnakeCaseMapper::class)]
final class UpdateUserData extends Data
{
    public function __construct(
        #[Max(255)]
        public Optional|string $name,
        public Optional|Designation $designation,
        #[Regex(AccountRules::PHONE_PATTERN)]
        public Optional|string|null $phone,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return AccountRules::messages();
    }
}
