<?php

namespace Modules\Shared\Patient\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Shared\Patient\Models\PatientMaster;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Patients of every branch. Filters: `filter[search]` (name, phone, patient no, national id), `filter[patient_no]`,
 * `filter[phone]`. Sorts: `name`, `created_at` (default newest first). `per_page` up to 100.
 */
final class ListPatients
{
    /**
     * @return LengthAwarePaginator<int, PatientMaster>
     */
    public function get(Request $request): LengthAwarePaginator
    {
        return QueryBuilder::for(PatientMaster::class, $request)
            ->allowedFilters(
                AllowedFilter::callback('search', fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                    ->whereLike('name', "%{$search}%")
                    ->orWhereLike('phone', "%{$search}%")
                    ->orWhereLike('patient_no', "%{$search}%")
                    ->orWhereLike('national_id', "%{$search}%")))->delimiter(''),
                AllowedFilter::exact('patient_no')->delimiter(''),
                AllowedFilter::exact('phone')->delimiter(''),
            )
            ->allowedSorts('name', 'created_at')
            ->defaultSort('-created_at')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100))
            ->withQueryString();
    }
}
