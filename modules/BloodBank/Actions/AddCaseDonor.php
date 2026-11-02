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
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Queries\ShowCase;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Contracts\InvoiceIssuer;
use Modules\Shared\Billing\Data\AdditionalInvoiceData;
use Modules\Shared\Billing\Exceptions\InvoiceNoTaken;

/**
 * Adds a donor to an open case after registration (a replacement for a deferred donor, or one more). The extra
 * donor is charged on its own bill: the vendor's invoice number, the case patient and the case bill's referral,
 * for the fixed additional-donor charge item. Registering a new donor, the bill and the case donor row are one
 * transaction.
 */
final class AddCaseDonor
{
    public const string CHARGE_TEST_ITEM_CODE = 'BB-ADDL-DONOR';

    public function __construct(
        private readonly InvoiceIssuer $invoiceIssuer,
        private readonly RegisterDonor $registerDonor,
        private readonly AuditLogger $auditLogger,
        private readonly ShowCase $showCase,
    ) {}

    /**
     * @throws CaseNotOpen
     * @throws DonorAlreadyInCase
     * @throws InvoiceNoTaken
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

            $donorId = $data->donor === null ? $data->donorId : $this->registerDonor->handle($actor, $data->donor)->id;

            $invoice = $this->invoiceIssuer->issueAdditional($actor, new AdditionalInvoiceData(
                sourceBillingId: (int) $case->billing_id,
                invoiceNo: $data->invoiceNo,
                testItemCode: self::CHARGE_TEST_ITEM_CODE,
            ));

            $caseDonor = $case->caseDonors()->create([
                'donor_id' => $donorId,
                'billing_id' => $invoice->billingId,
                'sequence_no' => (int) $case->caseDonors()->max('sequence_no') + 1,
                'status' => CaseDonorStatus::Registered,
            ]);

            $this->auditLogger->record(BloodBankAuditAction::CaseDonorAdded, $case, newValues: [
                'case_no' => $case->case_no,
                'case_donor_id' => $caseDonor->id,
                'donor_id' => $donorId,
                'sequence_no' => $caseDonor->sequence_no,
                'invoice_no' => $invoice->invoiceNo,
                'amount' => $invoice->amount,
            ]);

            return $case;
        });

        return $this->showCase->get($case);
    }
}
