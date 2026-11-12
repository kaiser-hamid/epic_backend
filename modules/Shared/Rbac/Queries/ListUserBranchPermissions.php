<?php

namespace Modules\Shared\Rbac\Queries;

use Illuminate\Support\Collection;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Rbac\Models\UserBranchPermission;
use Modules\Shared\Rbac\Services\RoleMatcher;

/**
 * What a user holds in each branch, with the role templates that the permissions match.
 */
final class ListUserBranchPermissions
{
    public function __construct(
        private readonly ListRoles $listRoles,
        private readonly RoleMatcher $roleMatcher,
    ) {}

    /**
     * @return Collection<int, array{branch: Branch, permissions: list<string>, matched_roles: list<array{id: int, name: string}>, other_permissions: list<string>}>
     */
    public function get(User $user): Collection
    {
        $roles = $this->listRoles->get();

        return UserBranchPermission::query()
            ->with(['branch.modules', 'permission'])
            ->where('user_id', $user->getKey())
            ->get()
            ->groupBy('branch_id')
            ->map(function (Collection $grants) use ($roles): array {
                $permissions = $grants->pluck('permission.name')->sort()->values()->all();

                return [
                    'branch' => $grants->first()->branch,
                    'permissions' => $permissions,
                    ...$this->roleMatcher->match($permissions, $roles),
                ];
            })
            ->sortBy(fn (array $group): string => $group['branch']->name)
            ->values();
    }
}
