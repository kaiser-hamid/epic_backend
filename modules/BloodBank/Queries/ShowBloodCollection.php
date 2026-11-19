<?php

namespace Modules\BloodBank\Queries;

use Illuminate\Database\Eloquent\Builder;
use Modules\BloodBank\Data\BloodCollectionLookupData;
use Modules\BloodBank\Data\BloodCollectionRow;
use Modules\BloodBank\Data\BloodCollectionView;
use Modules\BloodBank\Data\UnitView;
use Modules\BloodBank\Enums\CaseService;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Exceptions\CaseNotFound;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Unit;
use Modules\BloodBank\Services\BagPlan;
use Modules\BloodBank\Services\BloodCollectionGate;
use Modules\BloodBank\Services\UnitLabelCommands;
use Modules\Shared\Billing\Contracts\InvoiceDirectory;
use Modules\Shared\Branch\Contracts\CurrentBranch;
use Modules\Shared\Patient\Contracts\PatientDirectory;
use Modules\Shared\Patient\Data\PatientSummary;
use Modules\Shared\Printing\Models\PrintLog;

/**
 * The blood collection screen: the session branch's case (found by an invoice or donor no scan, or opened
 * directly), its patient and invoice, one row per case donor with its bag and whether blood can be collected, and
 * the plan for the next bag. A donor is collectable only while the case still needs something. A Therapeutic
 * Phlebotomy case shows its record instead (or that it can be recorded).
 */
final class ShowBloodCollection
{
    public function __construct(
        private readonly CurrentBranch $currentBranch,
        private readonly InvoiceDirectory $invoices,
        private readonly PatientDirectory $patients,
        private readonly BloodCollectionGate $collectionGate,
        private readonly BagPlan $bagPlan,
        private readonly UnitLabelCommands $labelCommands,
    ) {}

    /**
     * An invoice scan shows every donor of the case; a donor no scan shows only that donor, on the latest open
     * case of this branch they are registered on.
     *
     * @throws CaseNotFound
     */
    public function find(BloodCollectionLookupData $data): BloodCollectionView
    {
        if ($data->donorNo !== null) {
            $caseDonor = CaseDonor::query()
                ->whereHas('donor', fn (Builder $query) => $query->where('donor_no', $data->donorNo))
                ->whereHas('patientCase', fn (Builder $query) => $query
                    ->where('branch_id', $this->currentBranch->id())
                    ->where('status', CaseStatus::Open))
                ->with('patientCase')
                ->latest('id')
                ->first() ?? throw new CaseNotFound;

            return $this->get($caseDonor->patientCase, $caseDonor->id);
        }

        $case = PatientCase::query()
            ->where('branch_id', $this->currentBranch->id())
            ->billedBy($this->invoices->idForInvoiceNo((string) $data->invoiceNo) ?? throw new CaseNotFound)
            ->latest('id')
            ->first() ?? throw new CaseNotFound;

        return $this->get($case);
    }

    /**
     * @param  int|null  $caseDonorId  limits the rows to this case donor
     * @param  int|null  $collectedCollectionId  the bag just drawn by CollectBlood
     * @param  array<int, PrintLog>  $collectedUnitPrints  the label prints CollectBlood logged, keyed by unit id
     */
    public function get(PatientCase $case, ?int $caseDonorId = null, ?int $collectedCollectionId = null, array $collectedUnitPrints = []): BloodCollectionView
    {
        $case->load(['components.componentType', 'caseDonors.donor', 'caseDonors.collections.collector', 'phlebotomy.performer']);

        $isOpen = $case->status === CaseStatus::Open;
        $bagPlan = $isOpen ? $this->bagPlan->for($case) : [];

        $donors = $case->caseDonors
            ->when($caseDonorId !== null, fn ($caseDonors) => $caseDonors->where('id', $caseDonorId))
            ->sortBy('sequence_no')
            ->map(fn (CaseDonor $caseDonor): BloodCollectionRow => new BloodCollectionRow(
                $caseDonor,
                $this->collectionGate->collectionFor($caseDonor),
                $bagPlan !== [] && $this->collectionGate->refusal($caseDonor) === null,
            ))
            ->values()
            ->all();

        $patient = $this->patients->summaries([$case->patient_master_id])[$case->patient_master_id] ?? null;

        return new BloodCollectionView(
            $case,
            $patient,
            $case->billing_id === null ? null : ($this->invoices->summaries([$case->billing_id])[$case->billing_id] ?? null),
            $donors,
            $bagPlan,
            $collectedCollectionId,
            $this->collectedUnits($collectedUnitPrints, $patient),
            $isOpen && $case->service === CaseService::TherapeuticPhlebotomy,
        );
    }

    /**
     * The units just made, in creation order, each with its logged print and label printer command.
     *
     * @param  array<int, PrintLog>  $prints
     * @return list<UnitView>
     */
    private function collectedUnits(array $prints, ?PatientSummary $patient): array
    {
        if ($prints === []) {
            return [];
        }

        $views = Unit::query()
            ->whereKey(array_keys($prints))
            ->with(['componentType', 'storageLocation', 'collection.caseDonor.donor', 'patientCase'])
            ->orderBy('id')
            ->get()
            ->map(fn (Unit $unit): UnitView => new UnitView($unit, $unit->patientCase === null ? null : $patient, labelPrint: $prints[$unit->id]))
            ->all();

        $commands = $this->labelCommands->forUnits($views);

        return array_map(
            fn (UnitView $view): UnitView => new UnitView($view->unit, $view->patient, labelPrint: $view->labelPrint, labelCommand: $commands[$view->unit->id]),
            $views,
        );
    }
}
