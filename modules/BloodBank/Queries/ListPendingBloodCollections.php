<?php

namespace Modules\BloodBank\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\BloodBank\Data\CaseView;
use Modules\BloodBank\Enums\CaseDonorSource;
use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Enums\CaseService;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Services\BagPlan;
use Modules\Shared\Billing\Contracts\InvoiceDirectory;
use Modules\Shared\Branch\Contracts\CurrentBranch;
use Modules\Shared\Patient\Contracts\PatientDirectory;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * The "Pending" tab: open cases of the session's branch with a donor accepted at serology (`eligible`), i.e.
 * cleared to donate and not yet collected, that still need a unit (a non-empty bag plan; a case already holding
 * everything it was billed for is left out), open cases with a stock unit accepted at serology whose patient
 * label is not printed yet, and open Therapeutic Phlebotomy cases (not done yet).
 * Filter: `filter[care_type]`. Sorts: `created_at` (default oldest first), `case_no`. `per_page` up to 100.
 */
final class ListPendingBloodCollections
{
    public function __construct(
        private readonly CurrentBranch $currentBranch,
        private readonly PatientDirectory $patients,
        private readonly InvoiceDirectory $invoices,
        private readonly BagPlan $bagPlan,
    ) {}

    /**
     * @return LengthAwarePaginator<int, CaseView>
     */
    public function get(Request $request): LengthAwarePaginator
    {
        $baseQuery = PatientCase::query()
            ->where('branch_id', $this->currentBranch->id())
            ->where('status', CaseStatus::Open)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $this->bagPlan->whereNeedsUnits(
                    $query->whereHas('caseDonors', fn (Builder $query) => $query
                        ->where('source', CaseDonorSource::Donor)
                        ->where('status', CaseDonorStatus::Eligible)),
                ))
                ->orWhereHas('caseDonors', fn (Builder $query) => $query
                    ->where('source', CaseDonorSource::Stock)
                    ->where('status', CaseDonorStatus::Eligible))
                ->orWhere('service', CaseService::TherapeuticPhlebotomy))
            ->with('components.componentType')
            ->withCount('caseDonors');

        $cases = QueryBuilder::for($baseQuery, $request)
            ->allowedFilters(AllowedFilter::exact('care_type'))
            ->allowedSorts('created_at', 'case_no')
            ->defaultSort('created_at')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100))
            ->withQueryString();

        $patients = $this->patients->summaries($cases->getCollection()->pluck('patient_master_id')->unique()->values()->all());
        $invoices = $this->invoices->summaries($cases->getCollection()->pluck('billing_id')->filter()->unique()->values()->all());

        return $cases->through(fn (PatientCase $case): CaseView => new CaseView(
            $case,
            $patients[$case->patient_master_id] ?? null,
            $invoices[$case->billing_id] ?? null,
        ));
    }
}
