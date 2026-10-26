<?php

namespace Modules\Shared\Rbac\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shared\Branch\Models\Branch;

/**
 * What a user holds in one branch.
 *
 * @property array{branch: Branch, permissions: list<string>} $resource
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
            'branch' => ['id' => $branch->id, 'code' => $branch->code, 'name' => $branch->name],
            'permissions' => $this->resource['permissions'],
        ];
    }
}
