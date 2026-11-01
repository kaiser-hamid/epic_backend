<?php

namespace Modules\BloodBank\Queries;

use Modules\BloodBank\Data\DonorExaminationLookupData;
use Modules\BloodBank\Data\DonorExaminationRow;
use Modules\BloodBank\Data\DonorExaminationView;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Exceptions\CaseNotFound;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Services\DonorExaminationLock;
use Modules\Shared\Billing\Contracts\InvoiceDirectory;
use Modules\Shared\Branch\Contracts\CurrentBranch;
use Modules\Shared\Patient\Contracts\PatientDirectory;

/**
 * The donor examination screen: the session branch's case (found by an invoice scan, or opened directly), its
 * patient and invoice, and one row per case donor with its examination, decision and whether it can be saved.
 */
final class ShowDonorExamination
{
    public function __construct(
        private readonly CurrentBranch $currentBranch,
        private readonly InvoiceDirectory $invoices,
        private readonly PatientDirectory $patients,
        private readonly DonorExaminationLock $examinationLock,
    ) {}

    /**
     * @throws CaseNotFound
     */
    public function find(DonorExaminationLookupData $data): DonorExaminationView
    {
        $billingId = $this->invoices->idForInvoiceNo($data->invoiceNo) ?? throw new CaseNotFound;

        $case = PatientCase::query()
            ->where('branch_id', $this->currentBranch->id())
            ->billedBy($billingId)
            ->latest('id')
            ->first() ?? throw new CaseNotFound;

        return $this->get($case);
    }

    public function get(PatientCase $case): DonorExaminationView
    {
        $case->load(['components.componentType', 'caseDonors.donor', 'caseDonors.assessor', 'caseDonors.screening.examiner', 'caseDonors.samples']);

        $donors = $case->caseDonors
            ->sortBy('sequence_no')
            ->map(fn (CaseDonor $caseDonor): DonorExaminationRow => new DonorExaminationRow(
                $caseDonor,
                $case->status === CaseStatus::Open && ! $this->examinationLock->isLocked($caseDonor),
            ))
            ->values()
            ->all();

        return new DonorExaminationView(
            $case,
            $this->patients->summaries([$case->patient_master_id])[$case->patient_master_id] ?? null,
            $case->billing_id === null ? null : ($this->invoices->summaries([$case->billing_id])[$case->billing_id] ?? null),
            $donors,
        );
    }
}
