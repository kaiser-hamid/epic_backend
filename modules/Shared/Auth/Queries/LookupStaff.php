<?php

namespace Modules\Shared\Auth\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Support\Data\SearchLookupData;

/**
 * Active staff whose employee id or name contains the search, for picking who referred a patient. An exact
 * employee id match comes first, then by name. Capped at 20 rows.
 */
final class LookupStaff
{
    private const LIMIT = 20;

    /**
     * @return Collection<int, User>
     */
    public function get(SearchLookupData $data): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->whereLike('employee_id', "%{$data->search}%")
                ->orWhereLike('name', "%{$data->search}%"))
            ->orderByRaw('upper(employee_id) = ? desc', [mb_strtoupper($data->search)])
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get();
    }
}
