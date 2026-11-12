<?php

namespace Modules\Shared\Rbac\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;

/**
 * One row of the Permissions screen's user list. `permission_count` is the total over all branches;
 * `matched_roles` are display-only role templates, `other_permission_count` the permissions they don't explain.
 *
 * @property array{user: User, permission_count: int, branches: list<array{branch: Branch, permission_count: int, matched_roles: list<array{id: int, name: string}>, other_permission_count: int}>} $resource
 */
final class UserAccessResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource['user'];

        return [
            'id' => $user->id,
            'employee_id' => $user->employee_id,
            'name' => $user->name,
            'username' => $user->username,
            'designation' => $user->designation?->value,
            'is_active' => $user->is_active,
            'is_super_admin' => $user->is_super_admin,
            'permission_count' => $this->resource['permission_count'],
            'branches' => array_map(fn (array $branch): array => [
                'branch' => ['id' => $branch['branch']->id, 'code' => $branch['branch']->code, 'name' => $branch['branch']->name],
                'permission_count' => $branch['permission_count'],
                'matched_roles' => $branch['matched_roles'],
                'other_permission_count' => $branch['other_permission_count'],
            ], $this->resource['branches']),
        ];
    }
}
