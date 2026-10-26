<?php

namespace Modules\Shared\Rbac\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Present;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\References\RouteParameterReference;

/**
 * A role template: its name and the permissions the admin UI pre-checks when it is picked.
 */
#[MapName(SnakeCaseMapper::class)]
final class RoleData extends Data
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        #[Max(100), Unique('sh_roles', 'name', ignore: new RouteParameterReference('role', 'id', nullable: true), withoutTrashed: true)]
        public string $name,
        #[Present]
        public array $permissions,
        #[Max(1000)]
        public ?string $description = null,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'permissions.*' => ['string', 'distinct', 'exists:sh_permissions,name'],
        ];
    }
}
