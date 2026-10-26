<?php

namespace Modules\Shared\Rbac\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shared\Rbac\Models\Permission;

/**
 * One catalog entry. The frontend groups the list by `module` then `group` to build the checkbox tree.
 *
 * @mixin Permission
 */
final class PermissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'module' => $this->module,
            'group' => $this->group,
            'description' => $this->description,
        ];
    }
}
