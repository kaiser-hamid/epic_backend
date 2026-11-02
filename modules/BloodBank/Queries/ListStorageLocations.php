<?php

namespace Modules\BloodBank\Queries;

use Illuminate\Database\Eloquent\Collection;
use Modules\BloodBank\Models\StorageLocation;
use Modules\Shared\Branch\Contracts\CurrentBranch;

/**
 * The session branch's active storage locations (fridges, freezers, agitator), by code. A small fixed list for
 * form dropdowns, so it is not paginated.
 */
final class ListStorageLocations
{
    public function __construct(private readonly CurrentBranch $currentBranch) {}

    /**
     * @return Collection<int, StorageLocation>
     */
    public function get(): Collection
    {
        return StorageLocation::query()
            ->where('branch_id', $this->currentBranch->id())
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
    }
}
