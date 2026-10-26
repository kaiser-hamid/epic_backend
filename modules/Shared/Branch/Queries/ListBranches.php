<?php

namespace Modules\Shared\Branch\Queries;

use Illuminate\Database\Eloquent\Collection;
use Modules\Shared\Branch\Models\Branch;

final class ListBranches
{
    /**
     * @return Collection<int, Branch>
     */
    public function get(): Collection
    {
        return Branch::query()->orderBy('name')->get();
    }
}
