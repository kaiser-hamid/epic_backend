<?php

namespace Modules\BloodBank\Actions;

use Illuminate\Support\Facades\DB;
use Modules\BloodBank\Data\CaseComponentData;
use Modules\BloodBank\Data\CaseDonorData;
use Modules\BloodBank\Data\CaseView;
use Modules\BloodBank\Data\RegisterCaseData;
use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Queries\ShowCase;
use Modules\BloodBank\Services\CaseNumber;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Auth\Exceptions\BranchInactive;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Contracts\InvoiceIssuer;
use Modules\Shared\Branch\Contracts\CurrentBranch;

/**
 * Registers a Blood Bank case from one form: Shared billing registers the patient (when new) and bills the
 * service, then the case is opened with its requested components and the donors the patient brought.
 * Everything is one transaction, so a failure leaves no bill, patient or donor behind.
 */
final class RegisterCase
{
    public function __construct(
        private readonly InvoiceIssuer $invoiceIssuer,
        private readonly RegisterDonor $registerDonor,
        private readonly CaseNumber $caseNumber,
        private readonly CurrentBranch $currentBranch,
        private readonly AuditLogger $auditLogger,
        private readonly ShowCase $showCase,
    ) {}

    /**
     * @throws BranchInactive
     */
    public function handle(User $actor, RegisterCaseData $data): CaseView
    {
        $branchId = $this->currentBranch->id() ?? throw new BranchInactive;

        $case = DB::transaction(function () use ($actor, $data, $branchId): PatientCase {
            $invoice = $this->invoiceIssuer->issue($actor, $data->billing); //Third party module call

            $case = PatientCase::create([
                'case_no' => $this->caseNumber->next(),
                'patient_master_id' => $invoice->patientId,
                'billing_id' => $invoice->billingId,
                'branch_id' => $branchId,
                'care_type' => $data->careType,
                'transfusion_reason' => $data->transfusionReason,
                'reference_note' => $data->referenceNote,
                'status' => CaseStatus::Open,
                'created_by' => $actor->id,
            ]);

            $case->components()->createMany(array_map(fn (CaseComponentData $component): array => [
                'component_type_id' => $component->componentTypeId,
                'quantity' => $component->quantity,
            ], $data->components));

            $donorIds = $this->attachDonors($actor, $case, $data->donors);

            $this->auditLogger->record(BloodBankAuditAction::CaseRegistered, $case, newValues: [
                ...$case->only(['case_no', 'patient_master_id', 'billing_id', 'care_type', 'transfusion_reason']),
                'invoice_no' => $invoice->invoiceNo,
                'components' => array_map(fn (CaseComponentData $component): array => $component->toArray(), $data->components),
                'donor_ids' => $donorIds,
            ]);

            return $case;
        });

        return $this->showCase->get($case);
    }

    /**
     * Links each donor to the case in form order (sequence 1, 2, …), registering new donors first.
     *
     * @param  list<CaseDonorData>  $donors
     * @return list<int>
     */
    private function attachDonors(User $actor, PatientCase $case, array $donors): array
    {
        $donorIds = [];

        foreach ($donors as $index => $caseDonor) {
            $donorId = $caseDonor->donor === null
                ? $caseDonor->donorId
                : $this->registerDonor->handle($actor, $caseDonor->donor)->id;

            $case->caseDonors()->create([
                'donor_id' => $donorId,
                'sequence_no' => $index + 1,
                'status' => CaseDonorStatus::Registered,
            ]);

            $donorIds[] = $donorId;
        }

        return $donorIds;
    }
}
