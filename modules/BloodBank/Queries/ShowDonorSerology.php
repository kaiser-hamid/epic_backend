<?php

namespace Modules\BloodBank\Queries;

use Illuminate\Database\Eloquent\Builder;
use Modules\BloodBank\Data\DonorSerologyLookupData;
use Modules\BloodBank\Data\DonorSerologyRow;
use Modules\BloodBank\Data\DonorSerologyView;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Enums\SampleSubject;
use Modules\BloodBank\Exceptions\CaseNotFound;
use Modules\BloodBank\Exceptions\DonorSampleNotFound;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Sample;
use Modules\BloodBank\Services\DonorSerologyGate;
use Modules\Shared\Billing\Contracts\InvoiceDirectory;
use Modules\Shared\Branch\Contracts\CurrentBranch;
use Modules\Shared\Patient\Contracts\PatientDirectory;

/**
 * The donor serology screen: the session branch's case (found by an invoice or sample scan, or opened directly),
 * its patient and invoice, and one row per case donor with its donor sample, current results, decision and
 * whether the form can be saved.
 */
final class ShowDonorSerology
{
    public function __construct(
        private readonly CurrentBranch $currentBranch,
        private readonly InvoiceDirectory $invoices,
        private readonly PatientDirectory $patients,
        private readonly DonorSerologyGate $serologyGate,
    ) {}

    /**
     * An invoice scan shows every donor of the case; a sample scan shows only the donor the sample was drawn from.
     *
     * @throws CaseNotFound
     * @throws DonorSampleNotFound
     */
    public function find(DonorSerologyLookupData $data): DonorSerologyView
    {
        if ($data->sampleNo !== null) {
            $sample = Sample::query()
                ->where('sample_no', $data->sampleNo)
                ->where('subject_type', SampleSubject::Donor)
                ->whereHas('patientCase', fn (Builder $query) => $query->where('branch_id', $this->currentBranch->id()))
                ->with('patientCase')
                ->first() ?? throw new DonorSampleNotFound;

            return $this->get($sample->patientCase, $sample->case_donor_id);
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
     */
    public function get(PatientCase $case, ?int $caseDonorId = null): DonorSerologyView
    {
        $case->load([
            'components.componentType', 'caseDonors.donor', 'caseDonors.assessor',
            'caseDonors.samples.results.testDefinition', 'caseDonors.samples.results.tester',
        ]);

        $donors = $case->caseDonors
            ->when($caseDonorId !== null, fn ($caseDonors) => $caseDonors->where('id', $caseDonorId))
            ->sortBy('sequence_no')
            ->map(fn (CaseDonor $caseDonor): DonorSerologyRow => new DonorSerologyRow(
                $caseDonor,
                $this->serologyGate->sampleFor($caseDonor),
                $case->status === CaseStatus::Open && $this->serologyGate->refusal($caseDonor) === null,
            ))
            ->values()
            ->all();

        return new DonorSerologyView(
            $case,
            $this->patients->summaries([$case->patient_master_id])[$case->patient_master_id] ?? null,
            $case->billing_id === null ? null : ($this->invoices->summaries([$case->billing_id])[$case->billing_id] ?? null),
            $donors,
        );
    }
}
