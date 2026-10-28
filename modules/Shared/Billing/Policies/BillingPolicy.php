<?php

namespace Modules\Shared\Billing\Policies;

use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Rbac\Contracts\AccessChecker;
use Modules\Shared\Rbac\Enums\SharedPermission;

/**
 * An invoice is visible only from the branch that issued it.
 */
final class BillingPolicy
{
    public function __construct(private readonly AccessChecker $accessChecker) {}

    public function view(User $user, Billing $billing): bool
    {
        return $this->accessChecker->allowsOnRecord($user, SharedPermission::BillingView, $billing->branch_id);
    }
}
