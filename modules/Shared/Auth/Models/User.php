<?php

namespace Modules\Shared\Auth\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Modules\Shared\Auth\Database\Factories\UserFactory;
use Modules\Shared\Rbac\Models\UserBranchPermission;

/**
 * A staff account. `is_super_admin` is IT/Developer only and is set solely by `php artisan auth:super-admin`,
 * so it is deliberately not fillable.
 */
#[Fillable(['employee_id', 'name', 'designation', 'username', 'phone', 'password', 'is_active', 'last_login_at', 'failed_login_attempts', 'locked_until', 'must_change_password', 'password_changed_at'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'failed_login_attempts' => 'integer',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasMany<UserBranchPermission, $this>
     */
    public function branchPermissions(): HasMany
    {
        return $this->hasMany(UserBranchPermission::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}
