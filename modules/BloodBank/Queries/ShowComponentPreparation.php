<?php

namespace Modules\BloodBank\Queries;

use Illuminate\Database\Eloquent\Builder;
use Modules\BloodBank\Data\BagView;
use Modules\BloodBank\Data\ComponentPreparationLookupData;
use Modules\BloodBank\Data\ComponentPreparationView;
use Modules\BloodBank\Data\UnitView;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Enums\PrintDocumentType;
use Modules\BloodBank\Exceptions\BagNotFound;
use Modules\BloodBank\Exceptions\CollectionNotInCase;
use Modules\BloodBank\Models\Collection;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Unit;
use Modules\BloodBank\Services\UnitPreparationGate;
use Modules\Shared\Branch\Contracts\CurrentBranch;
use Modules\Shared\Patient\Contracts\PatientDirectory;
use Modules\Shared\Printing\Contracts\PrintLogger;

/**
 * The component preparation screen: a bag of the session branch (found by its barcode, or opened from the
 * Pending tab), its donor, case and patient, the units made from it with their label print counts, and whether
 * it can be prepared now.
 */
final class ShowComponentPreparation
{
    public function __construct(
        private readonly CurrentBranch $currentBranch,
        private readonly PatientDirectory $patients,
        private readonly UnitPreparationGate $preparationGate,
        private readonly PrintLogger $printLogger,
    ) {}

    /**
     * @throws BagNotFound
     */
    public function find(ComponentPreparationLookupData $data): ComponentPreparationView
    {
        $collection = Collection::query()
            ->where('bag_no', $data->bagNo)
            ->whereHas('caseDonor.patientCase', fn (Builder $query) => $query->where('branch_id', $this->currentBranch->id()))
            ->with('caseDonor.patientCase')
            ->first() ?? throw new BagNotFound;

        return $this->get($collection->caseDonor->patientCase, $collection);
    }

    /**
     * @param  list<int>  $preparedUnitIds  units just made by PrepareUnits
     *
     * @throws CollectionNotInCase
     */
    public function get(PatientCase $case, Collection $collection, array $preparedUnitIds = []): ComponentPreparationView
    {
        $collection->load(['caseDonor.donor', 'collector', 'units.componentType', 'units.storageLocation', 'units.patientCase']);

        if ($collection->caseDonor->case_id !== $case->id) {
            throw new CollectionNotInCase;
        }

        $case->load('components.componentType');
        $collection->caseDonor->setRelation('patientCase', $case);
        $patient = $this->patients->summaries([$case->patient_master_id])[$case->patient_master_id] ?? null;

        $units = $collection->units->sortBy('id')->values();
        $labelPrintCounts = [];

        foreach ($units as $unit) {
            $labelPrintCounts[$unit->id] = $this->printLogger->printCount(PrintDocumentType::ComponentLabel, $unit);
        }

        return new ComponentPreparationView(
            new BagView($collection, $patient),
            $case,
            $units->map(fn (Unit $unit): UnitView => new UnitView(
                $unit->setRelation('collection', $collection),
                $unit->case_id === $case->id ? $patient : null,
            ))->all(),
            $labelPrintCounts,
            $this->preparationGate->heldCounts($case),
            $case->status === CaseStatus::Open && $this->preparationGate->refusal($collection) === null,
            $preparedUnitIds,
        );
    }
}
