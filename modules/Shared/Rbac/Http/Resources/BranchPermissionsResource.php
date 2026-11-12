<?php

namespace Modules\Shared\Rbac\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shared\Branch\Models\Branch;

/**
 * What a user holds in one branch. `matched_roles` are the role templates fully covered by `permissions`
 * (display only), and `other_permissions` are the ones no matched role explains. `branch.modules` lists the
 * optional modules the branch runs; a held permission of any other optional module allows nothing there.
 *
 * @property array{branch: Branch, permissions: list<string>, matched_roles: list<array{id: int, name: string}>, other_permissions: list<string>} $resource
 */
final class BranchPermissionsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $branch = $this->resource['branch'];

        return [
            'branch' => [
                'id' => $branch->id,
                'code' => $branch->code,
                'name' => $branch->name,
                'modules' => $branch->modules->pluck('module')->all(),
            ],
            'permissions' => $this->resource['permissions'],
            'matched_roles' => $this->resource['matched_roles'],
            'other_permissions' => $this->resource['other_permissions'],
        ];
    }
}
