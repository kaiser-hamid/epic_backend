<?php

namespace Modules\Shared\Rbac\Actions;

use Modules\Shared\Rbac\Contracts\PermissionDefinition;
use Modules\Shared\Rbac\Data\RoleData;
use Modules\Shared\Rbac\Models\Role;
use Modules\Shared\Rbac\Services\PermissionRegistry;

/**
 * Creates each module's default role templates that do not exist yet. A template is matched by name, including
 * deleted ones: once created, admins own it, so their edits are kept and a deleted default is not brought back.
 * Run after the permission catalog sync, so every permission exists.
 */
final class SeedRoleTemplates
{
    public function __construct(
        private readonly PermissionRegistry $registry,
        private readonly CreateRole $createRole,
    ) {}

    /**
     * @return list<string> names of the templates created
     */
    public function handle(): array
    {
        $created = [];

        foreach ($this->registry->roleTemplates() as $template) {
            $name = (string) $template->value;

            if (Role::withTrashed()->where('name', $name)->exists()) {
                continue;
            }

            $permissions = $template->permissions();
            $first = $permissions[0] ?? null;

            if (! $first instanceof PermissionDefinition) {
                continue;
            }

            $this->createRole->handle(new RoleData(
                name: $name,
                module: $first->module(),
                permissions: array_map(fn ($permission): string => (string) $permission->value, $permissions),
                description: $template->description(),
            ));

            $created[] = $name;
        }

        return $created;
    }
}
