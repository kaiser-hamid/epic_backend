<?php

namespace Modules\Shared\Auth\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shared\Auth\Data\UserProfile;

/**
 * The `me` payload the frontend gates its UI with. `permissions` apply to `branch` only.
 *
 * @property UserProfile $resource
 */
final class UserProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource->user;

        return [
            'user' => [
                'id' => $user->id,
                'employee_id' => $user->employee_id,
                'name' => $user->name,
                'username' => $user->username,
                'designation' => $user->designation,
            ],
            'branch' => [
                'id' => $this->resource->branch->id,
                'code' => $this->resource->branch->code,
                'name' => $this->resource->branch->name,
            ],
            'permissions' => $this->resource->permissions,
            'is_super_admin' => $user->is_super_admin,
            'must_change_password' => $user->must_change_password,
        ];
    }
}
