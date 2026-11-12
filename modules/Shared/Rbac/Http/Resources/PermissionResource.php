<?php

namespace Modules\Shared\Rbac\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shared\Rbac\Models\Permission;

/**
 * One catalog entry. The permission screen uses `module_label` (ordered by `module_order`) as its top-level
 * headings, with the module's role templates and their permissions below.
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
            'module_label' => $this->module_label,
            'module_order' => $this->module_order,
            'module_is_optional' => $this->module_is_optional,
            'group' => $this->group,
            'description' => $this->description,
        ];
    }
}
