<?php

namespace Modules\Shared\Rbac\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Modules\Shared\Rbac\Actions\CreateRole;
use Modules\Shared\Rbac\Actions\DeleteRole;
use Modules\Shared\Rbac\Actions\UpdateRole;
use Modules\Shared\Rbac\Data\RoleData;
use Modules\Shared\Rbac\Http\Resources\RoleResource;
use Modules\Shared\Rbac\Models\Role;
use Modules\Shared\Rbac\Queries\ListRoles;

/**
 * Role templates for the permission-assignment screen. Roles never grant access by themselves.
 */
final class RoleController
{
    public function __construct(
        private readonly ListRoles $listRoles,
        private readonly CreateRole $createRole,
        private readonly UpdateRole $updateRole,
        private readonly DeleteRole $deleteRole,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return RoleResource::collection($this->listRoles->get());
    }

    public function store(RoleData $data): RoleResource
    {
        return RoleResource::make($this->createRole->handle($data));
    }

    public function show(Role $role): RoleResource
    {
        return RoleResource::make($role->load('permissions'));
    }

    public function update(Role $role, RoleData $data): RoleResource
    {
        return RoleResource::make($this->updateRole->handle($role, $data));
    }

    public function destroy(Role $role): Response
    {
        $this->deleteRole->handle($role);

        return response()->noContent();
    }
}
