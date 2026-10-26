<?php

namespace Modules\Shared\Rbac\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Shared\Branch\Models\Branch;

/**
 * One grant: the user holds this permission in this branch only. History lives in the audit log.
 */
#[Table('sh_user_branch_permissions')]
#[Fillable(['user_id', 'branch_id', 'permission_id', 'granted_by'])]
final class UserBranchPermission extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Permission, $this>
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
