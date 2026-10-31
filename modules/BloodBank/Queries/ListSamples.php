<?php

namespace Modules\BloodBank\Queries;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\BloodBank\Data\SampleView;
use Modules\BloodBank\Data\SubjectView;
use Modules\BloodBank\Models\Sample;
use Modules\Shared\Branch\Contracts\CurrentBranch;
use Modules\Shared\Patient\Contracts\PatientDirectory;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Samples of the session branch's cases: the "Collected" tab, and a label barcode scan via `filter[sample_no]`.
 * Filters: `filter[sample_no]`, `filter[case_no]`, `filter[status]`, `filter[subject_type]`,
 * `filter[collected_from]` / `filter[collected_to]` (Y-m-d, inclusive). Sorts: `collected_at` (default newest
 * first), `sample_no`. `per_page` up to 100.
 */
final class ListSamples
{
    public function __construct(
        private readonly CurrentBranch $currentBranch,
        private readonly PatientDirectory $patients,
    ) {}

    /**
     * @return LengthAwarePaginator<int, SampleView>
     */
    public function get(Request $request): LengthAwarePaginator
    {
        $baseQuery = Sample::query()
            ->whereHas('patientCase', fn (Builder $query) => $query->where('branch_id', $this->currentBranch->id()))
            ->with(['patientCase', 'caseDonor.donor', 'collector']);

        $samples = QueryBuilder::for($baseQuery, $request)
            ->allowedFilters(
                AllowedFilter::exact('sample_no')->delimiter(''),
                AllowedFilter::callback('case_no', fn (Builder $query, string $caseNo) => $query
                    ->whereHas('patientCase', fn (Builder $query) => $query->where('case_no', $caseNo)))->delimiter(''),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('subject_type'),
                AllowedFilter::callback('collected_from', fn (Builder $query, string $date) => $query
                    ->where('collected_at', '>=', $this->date('collected_from', $date)->startOfDay())),
                AllowedFilter::callback('collected_to', fn (Builder $query, string $date) => $query
                    ->where('collected_at', '<=', $this->date('collected_to', $date)->endOfDay())),
            )
            ->allowedSorts('collected_at', 'sample_no')
            ->defaultSort('-collected_at')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100))
            ->withQueryString();

        $patients = $this->patients->summaries($samples->getCollection()->pluck('patientCase.patient_master_id')->unique()->values()->all());

        return $samples->through(fn (Sample $sample): SampleView => new SampleView($sample, new SubjectView(
            $sample->subject_type,
            $sample->caseDonor,
            $sample->case_donor_id === null ? ($patients[$sample->patientCase->patient_master_id] ?? null) : null,
        )));
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
