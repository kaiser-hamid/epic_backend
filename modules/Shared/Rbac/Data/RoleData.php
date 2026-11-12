<?php

namespace Modules\Shared\Rbac\Data;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Present;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\References\RouteParameterReference;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/**
 * A role template: its module, name and the permissions the admin UI pre-checks when it is picked.
 * Every permission must belong to the template's module.
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
        public string $module,
        #[Present]
        public array $permissions,
        #[Max(1000)]
        public ?string $description = null,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $module = $context->payload['module'] ?? null;

        return [
            'module' => ['required', 'string', 'exists:sh_permissions,module'],
            'permissions.*' => [
                'string',
                'distinct',
                Rule::exists('sh_permissions', 'name')->where('module', is_string($module) ? $module : ''),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'permissions.*.exists' => 'Each permission must exist and belong to the role\'s module.',
        ];
    }
}
