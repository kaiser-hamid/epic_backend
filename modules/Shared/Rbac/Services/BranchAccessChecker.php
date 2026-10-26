<?php

namespace Modules\Shared\Rbac\Services;

use BackedEnum;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Contracts\CurrentBranch;
use Modules\Shared\Rbac\Contracts\AccessChecker;
use Modules\Shared\Rbac\Models\UserBranchPermission;

final class BranchAccessChecker implements AccessChecker
{
    public function __construct(
        private readonly CurrentBranch $currentBranch,
        private readonly PermissionRegistry $registry,
    ) {}

    public function allows(User $user, BackedEnum|string $permission, ?int $branchId = null): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->is_super_admin) {
            return true;
        }

        $branchId ??= $this->currentBranch->id();

        if ($branchId === null) {
            return false;
        }

        return UserBranchPermission::query()
            ->where('user_id', $user->getKey())
            ->where('branch_id', $branchId)
            ->whereRelation('permission', 'name', $this->nameOf($permission))
            ->exists();
    }

    public function allowsOnRecord(User $user, BackedEnum|string $permission, int $recordBranchId): bool
    {
        $sessionBranchId = $this->currentBranch->id();

        if ($sessionBranchId === null || $sessionBranchId !== $recordBranchId) {
            return false;
        }

        return $this->allows($user, $permission, $sessionBranchId);
    }

    public function permissionsIn(User $user, int $branchId): array
    {
        if ($user->is_super_admin) {
            return $this->registry->names();
        }

        return UserBranchPermission::query()
            ->join('sh_permissions', 'sh_permissions.id', '=', 'sh_user_branch_permissions.permission_id')
            ->where('sh_user_branch_permissions.user_id', $user->getKey())
            ->where('sh_user_branch_permissions.branch_id', $branchId)
            ->orderBy('sh_permissions.name')
            ->pluck('sh_permissions.name')
            ->all();
    }

    private function nameOf(BackedEnum|string $permission): string
    {
        return $permission instanceof BackedEnum ? (string) $permission->value : $permission;
    }
}
