<?php

namespace Modules\BloodBank\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\BloodBank\Models\Donor;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Donors of every branch, to find a returning donor before registering a new one. Filters: `filter[search]`
 * (name, phone, donor no, national id), `filter[donor_no]`, `filter[phone]`. Sorts: `name`, `created_at`
 * (default newest first). `per_page` up to 100.
 */
final class ListDonors
{
    /**
     * @return LengthAwarePaginator<int, Donor>
     */
    public function get(Request $request): LengthAwarePaginator
    {
        return QueryBuilder::for(Donor::class, $request)
            ->allowedFilters(
                AllowedFilter::callback('search', fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                    ->whereLike('name', "%{$search}%")
                    ->orWhereLike('phone', "%{$search}%")
                    ->orWhereLike('donor_no', "%{$search}%")
                    ->orWhereLike('national_id', "%{$search}%")))->delimiter(''),
                AllowedFilter::exact('donor_no')->delimiter(''),
                AllowedFilter::exact('phone')->delimiter(''),
            )
            ->allowedSorts('name', 'created_at')
            ->defaultSort('-created_at')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100))
            ->withQueryString();
    }
}
