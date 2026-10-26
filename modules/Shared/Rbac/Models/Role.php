<?php

namespace Modules\Shared\Rbac\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Shared\Rbac\Database\Factories\RoleFactory;

/**
 * A permission template for the admin UI (e.g. "Cash"). It is never assigned to users and never checked at runtime.
 */
#[Table('sh_roles')]
#[Fillable(['name', 'description'])]
#[UseFactory(RoleFactory::class)]
final class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'sh_role_permissions');
    }
}
