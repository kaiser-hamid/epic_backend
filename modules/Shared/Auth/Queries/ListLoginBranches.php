<?php

namespace Modules\Shared\Auth\Queries;

use Illuminate\Database\Eloquent\Collection;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;

/**
 * Branches offered on the login form for a typed username: the active branches the user holds any
 * permission in, or every active branch for a super admin. Unknown, inactive and locked accounts all
 * get the same empty list so the response says nothing about the account's state.
 */
final class ListLoginBranches
{
    /**
     * @return Collection<int, Branch>
     */
    public function get(string $username): Collection
    {
        $user = User::query()->where('username', $username)->first();

        if ($user === null || ! $user->is_active || $user->isLocked()) {
            return new Collection;
        }

        return Branch::query()
            ->active()
            ->unless($user->is_super_admin, fn ($query) => $query->whereIn(
                'id',
                $user->branchPermissions()->select('branch_id'),
            ))
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }
}
