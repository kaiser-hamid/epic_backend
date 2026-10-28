<?php

namespace Modules\Shared\Billing\Queries;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Branch\Contracts\CurrentBranch;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Invoices of the session's branch only. Filters: `filter[invoice_no]`, `filter[patient_id]`, `filter[status]`,
 * `filter[billed_from]` / `filter[billed_to]` (Y-m-d, inclusive), `filter[search]` (patient name, phone,
 * patient no). Sorts: `billed_at` (default newest first), `invoice_no`. Include: `items.testItem`.
 * `per_page` up to 100.
 */
final class ListInvoices
{
    public function __construct(private readonly CurrentBranch $currentBranch) {}

    /**
     * @return LengthAwarePaginator<int, Billing>
     */
    public function get(Request $request): LengthAwarePaginator
    {
        $baseQuery = Billing::query()
            ->where('branch_id', $this->currentBranch->id())
            ->with(['patient', 'referral.doctor']);

        return QueryBuilder::for($baseQuery, $request)
            ->allowedFilters(
                AllowedFilter::exact('invoice_no')->delimiter(''),
                AllowedFilter::exact('patient_id', 'patient_master_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('billed_from', fn (Builder $query, string $date) => $query
                    ->where('billed_at', '>=', $this->date('billed_from', $date)->startOfDay())),
                AllowedFilter::callback('billed_to', fn (Builder $query, string $date) => $query
                    ->where('billed_at', '<=', $this->date('billed_to', $date)->endOfDay())),
                AllowedFilter::callback('search', fn (Builder $query, string $search) => $query->whereHas('patient', fn (Builder $query) => $query
                    ->whereLike('name', "%{$search}%")
                    ->orWhereLike('phone', "%{$search}%")
                    ->orWhereLike('patient_no', "%{$search}%")))->delimiter(''),
            )
            ->allowedSorts('billed_at', 'invoice_no')
            ->defaultSort('-billed_at')
            ->allowedIncludes('items.testItem')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100))
            ->withQueryString();
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
