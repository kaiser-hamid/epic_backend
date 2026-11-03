<?php

namespace Modules\BloodBank\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\BloodBank\Data\BloodIssueDonorRow;
use Modules\BloodBank\Data\BloodIssueLookupData;
use Modules\BloodBank\Data\BloodIssueUnitRow;
use Modules\BloodBank\Data\BloodIssueView;
use Modules\BloodBank\Data\UnitView;
use Modules\BloodBank\Enums\PrintDocumentType;
use Modules\BloodBank\Exceptions\CaseNotFound;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Unit;
use Modules\BloodBank\Services\BloodCollectionGate;
use Modules\BloodBank\Services\BloodIssueGate;
use Modules\BloodBank\Services\DonorSerologyGate;
use Modules\Shared\Billing\Contracts\InvoiceDirectory;
use Modules\Shared\Branch\Contracts\CurrentBranch;
use Modules\Shared\Patient\Contracts\PatientDirectory;
use Modules\Shared\Printing\Contracts\PrintLogger;

/**
 * The blood issue (Compatibility Test Report) screen: the session branch's case, found by an invoice or donor no
 * scan or opened directly, with everything recorded on it so far.
 */
final class ShowBloodIssue
{
    public function __construct(
        private readonly CurrentBranch $currentBranch,
        private readonly InvoiceDirectory $invoices,
        private readonly PatientDirectory $patients,
        private readonly BloodIssueGate $issueGate,
        private readonly DonorSerologyGate $serologyGate,
        private readonly BloodCollectionGate $collectionGate,
        private readonly PrintLogger $printLogger,
    ) {}

    /**
     * An invoice scan opens the case it bills (the case bill or an added donor's bill); a donor no scan opens
     * the latest case of this branch the donor is on, closed or not, so a report can be reprinted after issue.
     *
     * @throws CaseNotFound
     */
    public function find(BloodIssueLookupData $data): BloodIssueView
    {
        $query = PatientCase::query()->where('branch_id', $this->currentBranch->id());

        if ($data->donorNo !== null) {
            $query->whereHas('caseDonors.donor', fn (Builder $query) => $query->where('donor_no', $data->donorNo));
        } else {
            $query->billedBy($this->invoices->idForInvoiceNo((string) $data->invoiceNo) ?? throw new CaseNotFound);
        }

        return $this->get($query->latest('id')->first() ?? throw new CaseNotFound);
    }

    public function get(PatientCase $case): BloodIssueView
    {
        $case->load([
            'components.componentType',
            'caseDonors' => fn (HasMany $query) => $query->orderBy('sequence_no'),
            'caseDonors.donor',
            'caseDonors.assessor',
            'caseDonors.screening.examiner',
            'caseDonors.screening.postRecorder',
            'caseDonors.samples.results.testDefinition',
            'caseDonors.samples.results.tester',
            'caseDonors.collections.collector',
            'units' => fn (HasMany $query) => $query->orderBy('id'),
            'units.componentType',
            'units.storageLocation',
            'units.collection.caseDonor.donor',
            'units.crossmatches' => fn (HasMany $query) => $query->where('is_current', true),
            'units.crossmatches.tester',
            'units.crossmatches.reviewer',
        ]);

        $patient = $this->patients->summaries([$case->patient_master_id])[$case->patient_master_id] ?? null;
        $billingIds = $case->caseDonors->pluck('billing_id')->push($case->billing_id)->filter()->unique()->values()->all();
        $invoices = $this->invoices->summaries($billingIds);
        $patientSample = $this->issueGate->patientSampleFor($case);
        $patientSample?->results->loadMissing('tester');

        return new BloodIssueView(
            $case,
            $patient,
            $case->billing_id === null ? null : ($invoices[$case->billing_id] ?? null),
            $invoices,
            $patientSample,
            $this->issueGate->bloodGroupResult($patientSample),
            $case->caseDonors->map(fn (CaseDonor $caseDonor): BloodIssueDonorRow => new BloodIssueDonorRow(
                $caseDonor,
                $this->serologyGate->sampleFor($caseDonor),
                $this->collectionGate->collectionFor($caseDonor),
                $this->issueGate->needsPostVitals($caseDonor),
            ))->values()->all(),
            $case->units->map(function (Unit $unit) use ($case, $patient): BloodIssueUnitRow {
                $crossmatch = $unit->crossmatches->sortByDesc('version')->first();

                return new BloodIssueUnitRow(
                    new UnitView($unit->setRelation('patientCase', $case), $patient),
                    $crossmatch,
                    $crossmatch === null ? 0 : $this->printLogger->printCount(PrintDocumentType::CompatibilityReport, $crossmatch),
                    $this->issueGate->refusal($case, $unit) === null,
                );
            })->values()->all(),
        );
    }
}
