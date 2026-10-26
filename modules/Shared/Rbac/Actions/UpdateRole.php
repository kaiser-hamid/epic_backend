<?php

namespace Modules\Shared\Rbac\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Rbac\Data\RoleData;
use Modules\Shared\Rbac\Models\Permission;
use Modules\Shared\Rbac\Models\Role;

/**
 * Edits a template. Users who were given permissions from it keep exactly what they were given.
 */
final class UpdateRole
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Role $role, RoleData $data): Role
    {
        return DB::transaction(function () use ($role, $data): Role {
            $before = [
                'name' => $role->name,
                'description' => $role->description,
                'permissions' => $role->permissions()->orderBy('name')->pluck('name')->all(),
            ];

            $role->update(['name' => $data->name, 'description' => $data->description]);
            $role->permissions()->sync(Permission::query()->whereIn('name', $data->permissions)->pluck('id'));

            $this->auditLogger->record(AuditAction::RoleUpdated, $role, $before, [
                'name' => $role->name,
                'description' => $role->description,
                'permissions' => collect($data->permissions)->sort()->values()->all(),
            ]);

            return $role->load('permissions');
        });
    }
}
