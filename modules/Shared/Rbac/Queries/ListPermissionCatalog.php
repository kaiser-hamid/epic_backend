<?php

namespace Modules\Shared\Rbac\Queries;

use Illuminate\Database\Eloquent\Collection;
use Modules\Shared\Rbac\Models\Permission;

final class ListPermissionCatalog
{
    /**
     * @return Collection<int, Permission>
     */
    public function get(): Collection
    {
        return Permission::query()->orderBy('module')->orderBy('group')->orderBy('name')->get();
    }
}
