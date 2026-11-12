<?php

namespace Modules\Shared\Rbac\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Auth\Exceptions\CannotChangeOwnAccount;
use Modules\Shared\Auth\Exceptions\SuperAdminProtected;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Auth\Services\AccountGuard;
use Modules\Shared\Branch\Contracts\BranchModules;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Rbac\Data\SyncBranchPermissionsData;
use Modules\Shared\Rbac\Exceptions\ModuleNotEnabledForBranch;
use Modules\Shared\Rbac\Models\Permission;
use Modules\Shared\Rbac\Models\Role;
use Modules\Shared\Rbac\Models\UserBranchPermission;
use Modules\Shared\Rbac\Queries\ListRoles;
use Modules\Shared\Rbac\Services\PermissionRegistry;
use Modules\Shared\Rbac\Services\RoleMatcher;
use Spatie\LaravelData\Optional;

/**
 * Replaces what a user may do in one branch with exactly the given list. Other branches are untouched.
 * An admin may assign in any branch, but never to their own account (no self-escalation), and never a
 * permission of an optional module (e.g. Blood Bank) the branch does not run.
 */
final class SyncUserBranchPermissions
{
    public function __construct(
        private readonly AccountGuard $accountGuard,
        private readonly AuditLogger $auditLogger,
        private readonly ListRoles $listRoles,
        private readonly RoleMatcher $roleMatcher,
        private readonly PermissionRegistry $registry,
        private readonly BranchModules $branchModules,
    ) {}

    /**
     * @return array{branch: Branch, permissions: list<string>, matched_roles: list<array{id: int, name: string}>, other_permissions: list<string>}
     *
     * @throws SuperAdminProtected|CannotChangeOwnAccount|ModuleNotEnabledForBranch
     */
    public function handle(User $actor, User $user, Branch $branch, SyncBranchPermissionsData $data): array
    {
        $this->accountGuard->ensureCanManage($actor, $user);
        $this->ensureModulesRunIn($branch, $data->permissions);

        $permissions = DB::transaction(function () use ($actor, $user, $branch, $data): array {
            $wanted = Permission::query()->whereIn('name', $data->permissions)->pluck('id', 'name');

            $current = UserBranchPermission::query()
                ->join('sh_permissions', 'sh_permissions.id', '=', 'sh_user_branch_permissions.permission_id')
                ->where('sh_user_branch_permissions.user_id', $user->getKey())
                ->where('sh_user_branch_permissions.branch_id', $branch->getKey())
                ->pluck('sh_permissions.id', 'sh_permissions.name');

            $added = $wanted->diffKeys($current);
            $removed = $current->diffKeys($wanted);

            if ($removed->isNotEmpty()) {
                UserBranchPermission::query()
                    ->where('user_id', $user->getKey())
                    ->where('branch_id', $branch->getKey())
                    ->whereIn('permission_id', $removed->values())
                    ->delete();
            }

            foreach ($added as $permissionId) {
                UserBranchPermission::create([
                    'user_id' => $user->getKey(),
                    'branch_id' => $branch->getKey(),
                    'permission_id' => $permissionId,
                    'granted_by' => $actor->getKey(),
                ]);
            }

            if ($added->isNotEmpty() || $removed->isNotEmpty()) {
                $this->auditLogger->record(
                    AuditAction::UserBranchPermissionsSynced,
                    $user,
                    ['branch_id' => $branch->getKey(), 'removed' => $removed->keys()->sort()->values()->all()],
                    [
                        'branch_id' => $branch->getKey(),
                        'added' => $added->keys()->sort()->values()->all(),
                        ...$this->appliedRoles($data),
                    ],
                );
            }

            return $wanted->keys()->sort()->values()->all();
        });

        return [
            'branch' => $branch->load('modules'),
            'permissions' => $permissions,
            ...$this->roleMatcher->match($permissions, $this->listRoles->get()),
        ];
    }

    /**
     * @param  list<string>  $permissions
     *
     * @throws ModuleNotEnabledForBranch
     */
    private function ensureModulesRunIn(Branch $branch, array $permissions): void
    {
        $enabledModules = $this->branchModules->enabledIn($branch->getKey());
        $missingLabels = [];

        foreach ($permissions as $name) {
            $module = $this->registry->optionalModuleOf($name);

            if ($module !== null && ! in_array($module, $enabledModules, true)) {
                $missingLabels[$module] = $this->registry->find($name)?->moduleLabel() ?? $module;
            }
        }

        if ($missingLabels !== []) {
            throw new ModuleNotEnabledForBranch(array_values($missingLabels), $branch->name);
        }
    }

    /**
     * @return array{applied_roles?: list<string>}
     */
    private function appliedRoles(SyncBranchPermissionsData $data): array
    {
        if ($data->appliedRoleIds instanceof Optional || $data->appliedRoleIds === []) {
            return [];
        }

        return ['applied_roles' => Role::query()->whereKey($data->appliedRoleIds)->orderBy('name')->pluck('name')->all()];
    }
}
