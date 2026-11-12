<?php

namespace Modules\Shared\Rbac\Data;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Present;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

/**
 * The complete permission list the user should hold in one branch. Anything not listed is revoked.
 * `applied_role_ids` names the role templates the admin started from; it is recorded in the audit log only
 * and never changes what is granted.
 */
#[MapName(SnakeCaseMapper::class)]
final class SyncBranchPermissionsData extends Data
{
    /**
     * @param  list<string>  $permissions
     * @param  Optional|list<int>  $appliedRoleIds
     */
    public function __construct(
        #[Present]
        public array $permissions,
        public Optional|array $appliedRoleIds = new Optional,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'permissions.*' => ['string', 'distinct', 'exists:sh_permissions,name'],
            'applied_role_ids.*' => ['integer', 'distinct', Rule::exists('sh_roles', 'id')->withoutTrashed()],
        ];
    }
}
