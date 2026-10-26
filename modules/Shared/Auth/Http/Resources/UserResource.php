<?php

namespace Modules\Shared\Auth\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shared\Auth\Models\User;

/**
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'name' => $this->name,
            'username' => $this->username,
            'designation' => $this->designation,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'is_super_admin' => $this->is_super_admin,
            'is_locked' => $this->isLocked(),
            'locked_until' => $this->locked_until?->toIso8601String(),
            'must_change_password' => $this->must_change_password,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
