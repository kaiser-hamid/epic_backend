<?php

namespace Modules\Shared\Branch\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One optional module (e.g. "blood-bank") a branch runs.
 */
#[Table('sh_branch_modules')]
#[Fillable(['branch_id', 'module'])]
final class BranchModule extends Model
{
    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
