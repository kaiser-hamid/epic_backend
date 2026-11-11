<?php

namespace Modules\Shared\Auth\Services;

use Modules\Shared\Auth\Exceptions\CannotChangeOwnAccount;
use Modules\Shared\Auth\Exceptions\SuperAdminProtected;
use Modules\Shared\Auth\Models\User;

/**
 * Record-level rules for admin actions on accounts: super-admin accounts can only be changed by a super
 * admin, and sensitive actions (status, password, permissions) cannot be applied to your own account.
 */
final class AccountGuard
{
    public function isProtected(User $actor, User $target): bool
    {
        return $target->is_super_admin && ! $actor->is_super_admin;
    }

    public function canManage(User $actor, User $target): bool
    {
        return ! $this->isProtected($actor, $target) && ! $actor->is($target);
    }

    /**
     * @throws SuperAdminProtected
     */
    public function ensureNotProtected(User $actor, User $target): void
    {
        if ($this->isProtected($actor, $target)) {
            throw new SuperAdminProtected;
        }
    }

    /**
     * @throws SuperAdminProtected|CannotChangeOwnAccount
     */
    public function ensureCanManage(User $actor, User $target): void
    {
        $this->ensureNotProtected($actor, $target);

        if ($actor->is($target)) {
            throw new CannotChangeOwnAccount;
        }
    }
}
