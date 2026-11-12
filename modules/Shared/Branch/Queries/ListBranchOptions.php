<?php

namespace Modules\Shared\Branch\Queries;

use Illuminate\Database\Eloquent\Collection;
use Modules\Shared\Branch\Models\Branch;

/**
 * Active branches for pickers (assigning a user's permissions in a branch). Picker fields plus the optional
 * modules each branch runs, so the permission screen hides the modules a branch does not have.
 */
final class ListBranchOptions
{
    /**
     * @return Collection<int, Branch>
     */
    public function get(): Collection
    {
        return Branch::query()->active()->with('modules:id,branch_id,module')->orderBy('name')->get(['id', 'code', 'name']);
    }
}
