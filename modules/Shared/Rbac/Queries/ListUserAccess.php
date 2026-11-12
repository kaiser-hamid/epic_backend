<?php

namespace Modules\Shared\Rbac\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Auth\Queries\ListUsers;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Rbac\Models\Role;
use Modules\Shared\Rbac\Models\UserBranchPermission;
use Modules\Shared\Rbac\Services\RoleMatcher;

/**
 * The user list of the Permissions screen: each user with how many permissions they hold and, per branch,
 * the role templates those permissions match. Same filters, sort and paging as `ListUsers`.
 */
final class ListUserAccess
{
    public function __construct(
        private readonly ListUsers $listUsers,
        private readonly ListRoles $listRoles,
        private readonly RoleMatcher $roleMatcher,
    ) {}

    /**
     * @return LengthAwarePaginator<int, array{user: User, permission_count: int, branches: list<array{branch: Branch, permission_count: int, matched_roles: list<array{id: int, name: string}>, other_permission_count: int}>}>
     */
    public function get(Request $request): LengthAwarePaginator
    {
        $users = $this->listUsers->get($request);
        $users->getCollection()->load(['branchPermissions.branch', 'branchPermissions.permission']);
        $roles = $this->listRoles->get();

        /** @phpstan-ignore method.notFound (the concrete paginator has `through`) */
        return $users->through(fn (User $user): array => $this->summarize($user, $roles));
    }

    /**
     * @param  EloquentCollection<int, Role>  $roles
     * @return array{user: User, permission_count: int, branches: list<array{branch: Branch, permission_count: int, matched_roles: list<array{id: int, name: string}>, other_permission_count: int}>}
     */
    private function summarize(User $user, EloquentCollection $roles): array
    {
        $branches = $user->branchPermissions
            ->groupBy('branch_id')
            ->map(function (Collection $grants) use ($roles): array {
                /** @var UserBranchPermission $first */
                $first = $grants->first();
                $permissions = $grants->pluck('permission.name')->all();
                $match = $this->roleMatcher->match($permissions, $roles);

                return [
                    'branch' => $first->branch,
                    'permission_count' => count($permissions),
                    'matched_roles' => $match['matched_roles'],
                    'other_permission_count' => count($match['other_permissions']),
                ];
            })
            ->sortBy(fn (array $branch): string => $branch['branch']->name)
            ->values()
            ->all();

        return [
            'user' => $user,
            'permission_count' => $user->branchPermissions->count(),
            'branches' => $branches,
        ];
    }
}
