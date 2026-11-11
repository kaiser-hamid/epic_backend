<?php

namespace Modules\Shared\Auth\Policies;

use Modules\Shared\Auth\Models\User;
use Modules\Shared\Auth\Services\AccountGuard;
use Modules\Shared\Branch\Contracts\CurrentBranch;
use Modules\Shared\Rbac\Contracts\AccessChecker;
use Modules\Shared\Rbac\Enums\SharedPermission;

/**
 * What the signed-in admin may do to one account, for the `abilities` on `UserResource`. Combines the
 * admin's permission in the session branch with the AccountGuard rules the Actions enforce.
 *
 * Bound as scoped, so a user list resolves the admin's permissions once per request, not once per row.
 */
final class UserPolicy
{
    /** @var array<int, list<string>> */
    private array $granted = [];

    public function __construct(
        private readonly AccessChecker $accessChecker,
        private readonly CurrentBranch $currentBranch,
        private readonly AccountGuard $accountGuard,
    ) {}

    public function update(User $actor, User $user): bool
    {
        return $this->holds($actor, SharedPermission::UserUpdate) && ! $this->accountGuard->isProtected($actor, $user);
    }

    public function changeStatus(User $actor, User $user): bool
    {
        return $this->holds($actor, SharedPermission::UserDeactivate) && $this->accountGuard->canManage($actor, $user);
    }

    public function resetPassword(User $actor, User $user): bool
    {
        return $this->holds($actor, SharedPermission::UserResetPassword) && $this->accountGuard->canManage($actor, $user);
    }

    public function assignPermissions(User $actor, User $user): bool
    {
        return $this->holds($actor, SharedPermission::UserAssignPermissions) && $this->accountGuard->canManage($actor, $user);
    }

    private function holds(User $actor, SharedPermission $permission): bool
    {
        $this->granted[$actor->getKey()] ??= $this->grantedTo($actor);

        return in_array($permission->value, $this->granted[$actor->getKey()], true);
    }

    /**
     * @return list<string>
     */
    private function grantedTo(User $actor): array
    {
        $branchId = $this->currentBranch->id();

        if (! $actor->is_active || $branchId === null) {
            return [];
        }

        return $this->accessChecker->permissionsIn($actor, $branchId);
    }
}
