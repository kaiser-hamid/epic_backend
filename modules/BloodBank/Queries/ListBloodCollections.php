<?php

namespace Modules\BloodBank\Queries;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\BloodBank\Data\BagView;
use Modules\BloodBank\Models\Collection;
use Modules\Shared\Branch\Contracts\CurrentBranch;
use Modules\Shared\Patient\Contracts\PatientDirectory;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Blood bags of the session branch's cases: the "Collected" tab, and a bag barcode scan via `filter[bag_no]`.
 * Filters: `filter[bag_no]`, `filter[case_no]`, `filter[donor_no]`, `filter[status]`, `filter[collected_from]` /
 * `filter[collected_to]` (Y-m-d, inclusive). Sorts: `collected_at` (default newest first), `bag_no`. `per_page`
 * up to 100.
 */
final class ListBloodCollections
{
    public function __construct(
        private readonly CurrentBranch $currentBranch,
        private readonly PatientDirectory $patients,
    ) {}

    /**
     * @return LengthAwarePaginator<int, BagView>
     */
    public function get(Request $request): LengthAwarePaginator
    {
        $baseQuery = Collection::query()
            ->whereHas('caseDonor.patientCase', fn (Builder $query) => $query->where('branch_id', $this->currentBranch->id()))
            ->with(['caseDonor.patientCase', 'caseDonor.donor', 'collector']);

        $collections = QueryBuilder::for($baseQuery, $request)
            ->allowedFilters(
                AllowedFilter::exact('bag_no')->delimiter(''),
                AllowedFilter::callback('case_no', fn (Builder $query, string $caseNo) => $query
                    ->whereHas('caseDonor.patientCase', fn (Builder $query) => $query->where('case_no', $caseNo)))->delimiter(''),
                AllowedFilter::callback('donor_no', fn (Builder $query, string $donorNo) => $query
                    ->whereHas('caseDonor.donor', fn (Builder $query) => $query->where('donor_no', $donorNo)))->delimiter(''),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('collected_from', fn (Builder $query, string $date) => $query
                    ->where('collected_at', '>=', $this->date('collected_from', $date)->startOfDay())),
                AllowedFilter::callback('collected_to', fn (Builder $query, string $date) => $query
                    ->where('collected_at', '<=', $this->date('collected_to', $date)->endOfDay())),
            )
            ->allowedSorts('collected_at', 'bag_no')
            ->defaultSort('-collected_at')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100))
            ->withQueryString();

        $patients = $this->patients->summaries($collections->getCollection()->pluck('caseDonor.patientCase.patient_master_id')->unique()->values()->all());

        return $collections->through(fn (Collection $collection): BagView => new BagView(
            $collection,
            $patients[$collection->caseDonor->patientCase->patient_master_id] ?? null,
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
