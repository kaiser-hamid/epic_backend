<?php

namespace Modules\Shared\Rbac\Queries;

use Illuminate\Support\Collection;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Rbac\Models\UserBranchPermission;

final class ListUserBranchPermissions
{
    /**
     * @return Collection<int, array{branch: Branch, permissions: list<string>}>
     */
    public function get(User $user): Collection
    {
        return UserBranchPermission::query()
            ->with(['branch', 'permission'])
            ->where('user_id', $user->getKey())
            ->get()
            ->groupBy('branch_id')
            ->map(fn (Collection $grants): array => [
                'branch' => $grants->first()->branch,
                'permissions' => $grants->pluck('permission.name')->sort()->values()->all(),
            ])
            ->sortBy(fn (array $group): string => $group['branch']->name)
            ->values();
    }
}
