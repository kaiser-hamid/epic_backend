<?php

namespace Modules\Shared\Auth\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\AlphaDash;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Support\Validation\References\RouteParameterReference;

/**
 * Profile fields only. Status, password, lock and permissions each have their own action.
 */
#[MapName(SnakeCaseMapper::class)]
final class UpdateUserData extends Data
{
    public function __construct(
        #[Max(50), Unique('users', 'employee_id', ignore: new RouteParameterReference('user', 'id'))]
        public Optional|string $employeeId,
        #[Max(255)]
        public Optional|string $name,
        #[Max(50), AlphaDash, Unique('users', 'username', ignore: new RouteParameterReference('user', 'id'))]
        public Optional|string $username,
        #[Max(255)]
        public Optional|string|null $designation,
        #[Max(20)]
        public Optional|string|null $phone,
    ) {}
}
