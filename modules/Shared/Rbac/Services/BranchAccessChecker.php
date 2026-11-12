<?php

namespace Modules\Shared\Rbac\Services;

use BackedEnum;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Contracts\BranchModules;
use Modules\Shared\Branch\Contracts\CurrentBranch;
use Modules\Shared\Rbac\Contracts\AccessChecker;
use Modules\Shared\Rbac\Models\UserBranchPermission;

/**
 * A permission of an optional module (e.g. Blood Bank) allows nothing in a branch that does not run that
 * module, not even for a super admin; grants left over from before the module was switched off stay inert.
 */
final class BranchAccessChecker implements AccessChecker
{
    public function __construct(
        private readonly CurrentBranch $currentBranch,
        private readonly PermissionRegistry $registry,
        private readonly BranchModules $branchModules,
    ) {}

    public function allows(User $user, BackedEnum|string $permission, ?int $branchId = null): bool
    {
        if (! $user->is_active) {
            return false;
        }

        $name = $this->nameOf($permission);
        $branchId ??= $this->currentBranch->id();

        if ($branchId !== null && ! $this->moduleRunsIn($name, $branchId)) {
            return false;
        }

        if ($user->is_super_admin) {
            return true;
        }

        if ($branchId === null) {
            return false;
        }

        return UserBranchPermission::query()
            ->where('user_id', $user->getKey())
            ->where('branch_id', $branchId)
            ->whereRelation('permission', 'name', $name)
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
        $names = $user->is_super_admin
            ? $this->registry->names()
            : UserBranchPermission::query()
                ->join('sh_permissions', 'sh_permissions.id', '=', 'sh_user_branch_permissions.permission_id')
                ->where('sh_user_branch_permissions.user_id', $user->getKey())
                ->where('sh_user_branch_permissions.branch_id', $branchId)
                ->orderBy('sh_permissions.name')
                ->pluck('sh_permissions.name')
                ->all();

        $enabledModules = $this->branchModules->enabledIn($branchId);

        return array_values(array_filter($names, function (string $name) use ($enabledModules): bool {
            $module = $this->registry->optionalModuleOf($name);

            return $module === null || in_array($module, $enabledModules, true);
        }));
    }

    private function moduleRunsIn(string $permission, int $branchId): bool
    {
        $module = $this->registry->optionalModuleOf($permission);

        return $module === null || $this->branchModules->isEnabled($branchId, $module);
    }

    private function nameOf(BackedEnum|string $permission): string
    {
        return $permission instanceof BackedEnum ? (string) $permission->value : $permission;
    }
}
