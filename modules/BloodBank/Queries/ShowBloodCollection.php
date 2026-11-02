<?php

namespace Modules\BloodBank\Queries;

use Illuminate\Database\Eloquent\Builder;
use Modules\BloodBank\Data\BloodCollectionLookupData;
use Modules\BloodBank\Data\BloodCollectionRow;
use Modules\BloodBank\Data\BloodCollectionView;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Enums\PrintDocumentType;
use Modules\BloodBank\Exceptions\CaseNotFound;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Services\BloodCollectionGate;
use Modules\Shared\Billing\Contracts\InvoiceDirectory;
use Modules\Shared\Branch\Contracts\CurrentBranch;
use Modules\Shared\Patient\Contracts\PatientDirectory;
use Modules\Shared\Printing\Contracts\PrintLogger;

/**
 * The blood collection screen: the session branch's case (found by an invoice or donor no scan, or opened
 * directly), its patient and invoice, and one row per case donor with its bag, whether blood can be collected
 * and the bag label print count.
 */
final class ShowBloodCollection
{
    public function __construct(
        private readonly CurrentBranch $currentBranch,
        private readonly InvoiceDirectory $invoices,
        private readonly PatientDirectory $patients,
        private readonly BloodCollectionGate $collectionGate,
        private readonly PrintLogger $printLogger,
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
     */
    public function get(PatientCase $case, ?int $caseDonorId = null, ?int $collectedCollectionId = null): BloodCollectionView
    {
        $case->load(['components.componentType', 'caseDonors.donor', 'caseDonors.collections.collector']);

        $donors = $case->caseDonors
            ->when($caseDonorId !== null, fn ($caseDonors) => $caseDonors->where('id', $caseDonorId))
            ->sortBy('sequence_no')
            ->map(function (CaseDonor $caseDonor) use ($case): BloodCollectionRow {
                $collection = $this->collectionGate->collectionFor($caseDonor);

                return new BloodCollectionRow(
                    $caseDonor,
                    $collection,
                    $case->status === CaseStatus::Open && $this->collectionGate->refusal($caseDonor) === null,
                    $collection === null ? 0 : $this->printLogger->printCount(PrintDocumentType::BagLabel, $collection),
                );
            })
            ->values()
            ->all();

        return new BloodCollectionView(
            $case,
            $this->patients->summaries([$case->patient_master_id])[$case->patient_master_id] ?? null,
            $case->billing_id === null ? null : ($this->invoices->summaries([$case->billing_id])[$case->billing_id] ?? null),
            $donors,
            $collectedCollectionId,
        );
    }
}
