<?php

namespace Modules\BloodBank\Actions;

use Illuminate\Support\Facades\DB;
use Modules\BloodBank\Data\AddCaseDonorData;
use Modules\BloodBank\Data\CaseView;
use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Exceptions\CaseNotOpen;
use Modules\BloodBank\Exceptions\DonorAlreadyInCase;
use Modules\BloodBank\Exceptions\InvoiceLinkedToOtherCase;
use Modules\BloodBank\Exceptions\InvoiceNotActive;
use Modules\BloodBank\Exceptions\InvoiceNotFound;
use Modules\BloodBank\Exceptions\InvoiceNotInBranch;
use Modules\BloodBank\Exceptions\InvoicePatientMismatch;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Queries\ShowCase;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Contracts\InvoiceDirectory;
use Modules\Shared\Billing\Data\InvoiceSummary;
use Modules\Shared\Billing\Enums\BillingStatus;

/**
 * Adds a donor to an open case. Every donor references a bill already saved in billing (the case's own bill or
 * another one, e.g. an additional donor charge billed on New Bill Entry); Blood Bank never bills here and does
 * not look at what the bill contains. The bill must be an active bill of the case's branch, for the case's
 * patient, and not belong to another case. Registering a new donor and the case donor row are one transaction.
 */
final class AddCaseDonor
{
    public function __construct(
        private readonly InvoiceDirectory $invoices,
        private readonly RegisterDonor $registerDonor,
        private readonly AuditLogger $auditLogger,
        private readonly ShowCase $showCase,
    ) {}

    /**
     * @throws CaseNotOpen
     * @throws DonorAlreadyInCase
     * @throws InvoiceNotFound
     * @throws InvoiceNotInBranch
     * @throws InvoiceNotActive
     * @throws InvoicePatientMismatch
     * @throws InvoiceLinkedToOtherCase
     */
    public function handle(User $actor, PatientCase $case, AddCaseDonorData $data): CaseView
    {
        $case = DB::transaction(function () use ($actor, $case, $data): PatientCase {
            $case = PatientCase::query()->lockForUpdate()->findOrFail($case->id);

            if ($case->status !== CaseStatus::Open) {
                throw new CaseNotOpen;
            }

            if ($data->donorId !== null && $case->caseDonors()->where('donor_id', $data->donorId)->exists()) {
                throw new DonorAlreadyInCase;
            }

            $invoice = $this->invoiceFor($case, $data->invoiceNo);

            $donorId = $data->donor === null ? $data->donorId : $this->registerDonor->handle($actor, $data->donor)->id;

            $caseDonor = $case->caseDonors()->create([
                'donor_id' => $donorId,
                'billing_id' => $invoice->id,
                'sequence_no' => (int) $case->caseDonors()->max('sequence_no') + 1,
                'status' => CaseDonorStatus::Registered,
            ]);

            $this->auditLogger->record(BloodBankAuditAction::CaseDonorAdded, $case, newValues: [
                'case_no' => $case->case_no,
                'case_donor_id' => $caseDonor->id,
                'donor_id' => $donorId,
                'sequence_no' => $caseDonor->sequence_no,
                'billing_id' => $invoice->id,
                'invoice_no' => $invoice->invoiceNo,
            ]);

            return $case;
        });

        return $this->showCase->get($case);
    }

    /**
     * @throws InvoiceNotFound
     * @throws InvoiceNotInBranch
     * @throws InvoiceNotActive
     * @throws InvoicePatientMismatch
     * @throws InvoiceLinkedToOtherCase
     */
    private function invoiceFor(PatientCase $case, string $invoiceNo): InvoiceSummary
    {
        $invoice = $this->invoices->findByInvoiceNo($invoiceNo) ?? throw new InvoiceNotFound;

        if ($invoice->branchId !== $case->branch_id) {
            throw new InvoiceNotInBranch;
        }

        if ($invoice->status !== BillingStatus::Active) {
            throw new InvoiceNotActive;
        }

        if ($invoice->patientId !== $case->patient_master_id) {
            throw new InvoicePatientMismatch;
        }

        if (PatientCase::query()->whereKeyNot($case->id)->billedBy($invoice->id)->exists()) {
            throw new InvoiceLinkedToOtherCase;
        }

        return $invoice;
    }
}
