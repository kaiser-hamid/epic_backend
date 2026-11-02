<?php

namespace Modules\BloodBank\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\BloodBank\Data\BagView;
use Modules\BloodBank\Enums\CollectionStatus;
use Modules\BloodBank\Models\Collection;
use Modules\Shared\Branch\Contracts\CurrentBranch;
use Modules\Shared\Patient\Contracts\PatientDirectory;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * The "Pending" tab: collected bags of the session branch's cases with no units yet. Sorts: `collected_at`
 * (default oldest first), `bag_no`. `per_page` up to 100.
 */
final class ListPendingComponentPreparations
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
            ->where('status', CollectionStatus::Collected)
            ->whereDoesntHave('units')
            ->whereHas('caseDonor.patientCase', fn (Builder $query) => $query->where('branch_id', $this->currentBranch->id()))
            ->with(['caseDonor.patientCase', 'caseDonor.donor', 'collector']);

        $collections = QueryBuilder::for($baseQuery, $request)
            ->allowedSorts('collected_at', 'bag_no')
            ->defaultSort('collected_at')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100))
            ->withQueryString();

        $patients = $this->patients->summaries($collections->getCollection()->pluck('caseDonor.patientCase.patient_master_id')->unique()->values()->all());

        return $collections->through(fn (Collection $collection): BagView => new BagView(
            $collection,
            $patients[$collection->caseDonor->patientCase->patient_master_id] ?? null,
        ));
    }
}
