<?php

namespace Modules\Shared\TestCatalog\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Shared\TestCatalog\Data\LookupTestItemsData;
use Modules\Shared\TestCatalog\Models\TestItem;

/**
 * Active billable tests whose code or name contains the search, minus the ids already picked on the form.
 * An exact code match comes first, then by name. Capped at 20 rows.
 */
final class LookupTestItems
{
    private const LIMIT = 20;

    /**
     * @return Collection<int, TestItem>
     */
    public function get(LookupTestItemsData $data): Collection
    {
        return TestItem::query()
            ->where('is_active', true)
            ->whereNotIn('id', $data->excludeIds)
            ->where(fn (Builder $query) => $query
                ->whereLike('code', "%{$data->search}%")
                ->orWhereLike('name', "%{$data->search}%"))
            ->orderByRaw('upper(code) = ? desc', [mb_strtoupper($data->search)])
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get();
    }
}
