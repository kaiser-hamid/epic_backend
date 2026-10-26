<?php

namespace Modules\Shared\Rbac\Contracts;

use BackedEnum;
use Modules\Shared\Auth\Models\User;

/**
 * Answers "may this user do X in branch Y". The branch always comes first: a grant in one branch never
 * applies to another. Super admins pass every check. Roles are templates only and are never consulted.
 */
interface AccessChecker
{
    /**
     * Check a permission in the given branch, or in the session's branch when none is given.
     */
    public function allows(User $user, BackedEnum|string $permission, ?int $branchId = null): bool;

    /**
     * Check a permission against an existing record: the record must belong to the session's branch.
     */
    public function allowsOnRecord(User $user, BackedEnum|string $permission, int $recordBranchId): bool;

    /**
     * @return list<string>
     */
    public function permissionsIn(User $user, int $branchId): array;
}
