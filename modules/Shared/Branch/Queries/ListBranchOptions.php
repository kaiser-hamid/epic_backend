<?php

namespace Modules\Shared\Branch\Queries;

use Illuminate\Database\Eloquent\Collection;
use Modules\Shared\Branch\Models\Branch;

/**
 * Active branches for pickers (assigning a user's permissions in a branch). Picker fields only.
 */
final class ListBranchOptions
{
    /**
     * @return Collection<int, Branch>
     */
    public function get(): Collection
    {
        return Branch::query()->active()->orderBy('name')->get(['id', 'code', 'name']);
    }
}
