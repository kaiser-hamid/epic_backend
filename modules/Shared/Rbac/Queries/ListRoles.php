<?php

namespace Modules\Shared\Rbac\Queries;

use Illuminate\Database\Eloquent\Collection;
use Modules\Shared\Rbac\Models\Role;

final class ListRoles
{
    /**
     * @return Collection<int, Role>
     */
    public function get(): Collection
    {
        return Role::query()->with('permissions')->orderBy('name')->get();
    }
}
