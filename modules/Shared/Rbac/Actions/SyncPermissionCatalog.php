<?php

namespace Modules\Shared\Rbac\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Rbac\Models\Permission;
use Modules\Shared\Rbac\Models\UserBranchPermission;
use Modules\Shared\Rbac\Services\PermissionRegistry;

/**
 * Mirrors the code-defined permission enums into `sh_permissions`. A permission removed from code is
 * deleted only when nobody holds it and no role uses it; otherwise it is kept and reported.
 */
final class SyncPermissionCatalog
{
    public function __construct(private readonly PermissionRegistry $registry) {}

    /**
     * @return array{upserted: int, deleted: list<string>, kept: list<string>}
     */
    public function handle(): array
    {
        return DB::transaction(function (): array {
            $now = now();

            Permission::query()->upsert(
                array_map(fn ($permission): array => [
                    'name' => (string) $permission->value,
                    'module' => $permission->module(),
                    'group' => $permission->group(),
                    'description' => $permission->description(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $this->registry->all()),
                ['name'],
                ['module', 'group', 'description', 'updated_at'],
            );

            $stale = Permission::query()->whereNotIn('name', $this->registry->names())->get();
            $inUse = $stale->filter(fn (Permission $permission): bool => UserBranchPermission::query()->where('permission_id', $permission->getKey())->exists()
                || DB::table('sh_role_permissions')->where('permission_id', $permission->getKey())->exists());
            $deletable = $stale->diff($inUse);

            Permission::query()->whereKey($deletable->modelKeys())->delete();

            return [
                'upserted' => count($this->registry->all()),
                'deleted' => $deletable->pluck('name')->values()->all(),
                'kept' => $inUse->pluck('name')->values()->all(),
            ];
        });
    }
}
