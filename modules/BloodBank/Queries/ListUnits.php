<?php

namespace Modules\BloodBank\Queries;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\BloodBank\Data\UnitView;
use Modules\BloodBank\Models\Unit;
use Modules\Shared\Branch\Contracts\CurrentBranch;
use Modules\Shared\Patient\Contracts\PatientDirectory;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Blood units of the session branch: the stock tab, and a unit barcode scan via `filter[unit_no]`. Filters:
 * `filter[unit_no]`, `filter[bag_no]`, `filter[case_no]` (the case a unit is reserved for or issued to),
 * `filter[component_type_id]`, `filter[blood_group]`, `filter[status]`, `filter[storage_location_id]`,
 * `filter[expires_before]` (Y-m-d, inclusive). Sorts: `expires_at` (default soonest first), `prepared_at`,
 * `unit_no`. `per_page` up to 100.
 */
final class ListUnits
{
    public function __construct(
        private readonly CurrentBranch $currentBranch,
        private readonly PatientDirectory $patients,
    ) {}

    /**
     * @return LengthAwarePaginator<int, UnitView>
     */
    public function get(Request $request): LengthAwarePaginator
    {
        $baseQuery = Unit::query()
            ->where('branch_id', $this->currentBranch->id())
            ->with(['componentType', 'storageLocation', 'collection.caseDonor.donor', 'patientCase']);

        $units = QueryBuilder::for($baseQuery, $request)
            ->allowedFilters(
                AllowedFilter::exact('unit_no')->delimiter(''),
                AllowedFilter::callback('bag_no', fn (Builder $query, string $bagNo) => $query
                    ->whereHas('collection', fn (Builder $query) => $query->where('bag_no', $bagNo)))->delimiter(''),
                AllowedFilter::callback('case_no', fn (Builder $query, string $caseNo) => $query
                    ->whereHas('patientCase', fn (Builder $query) => $query->where('case_no', $caseNo)))->delimiter(''),
                AllowedFilter::exact('component_type_id'),
                AllowedFilter::exact('blood_group'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('storage_location_id'),
                AllowedFilter::callback('expires_before', fn (Builder $query, string $date) => $query
                    ->where('expires_at', '<=', $this->date('expires_before', $date)->endOfDay())),
            )
            ->allowedSorts('expires_at', 'prepared_at', 'unit_no')
            ->defaultSort('expires_at')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100))
            ->withQueryString();

        $patients = $this->patients->summaries($units->getCollection()->pluck('patientCase.patient_master_id')->filter()->unique()->values()->all());

        return $units->through(fn (Unit $unit): UnitView => new UnitView(
            $unit,
            $unit->patientCase === null ? null : ($patients[$unit->patientCase->patient_master_id] ?? null),
        ));
    }

    /**
     * @throws ValidationException
     */
    private function date(string $filter, string $value): CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (InvalidFormatException) {
            throw ValidationException::withMessages(["filter.{$filter}" => 'The date must be in Y-m-d format.']);
        }
    }
}
