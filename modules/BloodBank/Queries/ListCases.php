<?php

namespace Modules\BloodBank\Queries;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\BloodBank\Data\CaseView;
use Modules\BloodBank\Models\PatientCase;
use Modules\Shared\Billing\Contracts\InvoiceDirectory;
use Modules\Shared\Branch\Contracts\CurrentBranch;
use Modules\Shared\Patient\Contracts\PatientDirectory;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Cases of the session's branch only. Filters: `filter[case_no]`, `filter[invoice_no]` (the case bill or a
 * replacement donor's bill), `filter[search]` (patient name, phone, patient no), `filter[status]`,
 * `filter[care_type]`, `filter[registered_from]` / `filter[registered_to]` (Y-m-d, inclusive).
 * Sorts: `created_at` (default newest first), `case_no`. `per_page` up to 100.
 */
final class ListCases
{
    public function __construct(
        private readonly CurrentBranch $currentBranch,
        private readonly PatientDirectory $patients,
        private readonly InvoiceDirectory $invoices,
    ) {}

    /**
     * @return LengthAwarePaginator<int, CaseView>
     */
    public function get(Request $request): LengthAwarePaginator
    {
        $baseQuery = PatientCase::query()
            ->where('branch_id', $this->currentBranch->id())
            ->with('components.componentType')
            ->withCount('caseDonors');

        $cases = QueryBuilder::for($baseQuery, $request)
            ->allowedFilters(
                AllowedFilter::exact('case_no')->delimiter(''),
                AllowedFilter::callback('invoice_no', fn (Builder $query, string $invoiceNo) => $this->whereInvoice($query, $invoiceNo))->delimiter(''),
                AllowedFilter::callback('search', fn (Builder $query, string $search) => $query
                    ->whereIn('patient_master_id', $this->patients->idsMatching($search)))->delimiter(''),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('care_type'),
                AllowedFilter::callback('registered_from', fn (Builder $query, string $date) => $query
                    ->where('created_at', '>=', $this->date('registered_from', $date)->startOfDay())),
                AllowedFilter::callback('registered_to', fn (Builder $query, string $date) => $query
                    ->where('created_at', '<=', $this->date('registered_to', $date)->endOfDay())),
            )
            ->allowedSorts('created_at', 'case_no')
            ->defaultSort('-created_at')
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

    /**
     * @param  Builder<PatientCase>  $query
     */
    private function whereInvoice(Builder $query, string $invoiceNo): void
    {
        $billingId = $this->invoices->idForInvoiceNo($invoiceNo);

        if ($billingId === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->billedBy($billingId);
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
