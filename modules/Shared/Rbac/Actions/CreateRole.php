<?php

namespace Modules\Shared\Rbac\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Rbac\Data\RoleData;
use Modules\Shared\Rbac\Models\Permission;
use Modules\Shared\Rbac\Models\Role;

final class CreateRole
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(RoleData $data): Role
    {
        return DB::transaction(function () use ($data): Role {
            $role = Role::create(['name' => $data->name, 'description' => $data->description]);
            $role->permissions()->sync(Permission::query()->whereIn('name', $data->permissions)->pluck('id'));

            $this->auditLogger->record(AuditAction::RoleCreated, $role, newValues: [
                'name' => $role->name,
                'permissions' => collect($data->permissions)->sort()->values()->all(),
            ]);

            return $role->load('permissions');
        });
    }
}
