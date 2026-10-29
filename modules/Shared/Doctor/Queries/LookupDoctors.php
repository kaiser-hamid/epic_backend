<?php

namespace Modules\Shared\Doctor\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Shared\Doctor\Models\Doctor;
use Modules\Shared\Support\Data\SearchLookupData;

/**
 * Active doctors whose code or name contains the search, for picking the referring doctor. An exact code match
 * comes first, then by name. Capped at 20 rows.
 */
final class LookupDoctors
{
    private const LIMIT = 20;

    /**
     * @return Collection<int, Doctor>
     */
    public function get(SearchLookupData $data): Collection
    {
        return Doctor::query()
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->whereLike('code', "%{$data->search}%")
                ->orWhereLike('name', "%{$data->search}%"))
            ->orderByRaw('upper(code) = ? desc', [mb_strtoupper($data->search)])
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get();
    }
}
