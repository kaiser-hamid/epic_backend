<?php

namespace Modules\Shared\Billing\Services;

use LogicException;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Actions\CreateInvoice;
use Modules\Shared\Billing\Contracts\InvoiceIssuer;
use Modules\Shared\Billing\Data\AdditionalInvoiceData;
use Modules\Shared\Billing\Data\CreateInvoiceData;
use Modules\Shared\Billing\Data\InvoiceItemData;
use Modules\Shared\Billing\Data\InvoiceReferralData;
use Modules\Shared\Billing\Data\IssuedInvoice;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\TestCatalog\Models\TestItem;

final class ActionInvoiceIssuer implements InvoiceIssuer
{
    public function __construct(private readonly CreateInvoice $createInvoice) {}

    public function issue(User $actor, CreateInvoiceData $data): IssuedInvoice
    {
        return $this->issued($this->createInvoice->handle($actor, $data));
    }

    public function issueAdditional(User $actor, AdditionalInvoiceData $data): IssuedInvoice
    {
        $source = Billing::query()->with('referral')->findOrFail($data->sourceBillingId);
        $testItemId = TestItem::query()->where('code', $data->testItemCode)->where('is_active', true)->value('id')
            ?? throw new LogicException("Test item {$data->testItemCode} is missing or inactive; run the migrations.");

        $referral = $source->referral;

        return $this->issued($this->createInvoice->handle($actor, new CreateInvoiceData(
            items: [new InvoiceItemData($testItemId, $data->quantity)],
            referral: new InvoiceReferralData(
                doctorId: $referral?->doctor_id,
                isSelfReferral: $referral === null || $referral->is_self_referral,
                secondDoctorId: $referral?->second_doctor_id,
                remark: $referral?->remark,
                referredByStaffId: $referral?->referred_by_staff_id,
            ),
            patientId: $source->patient_master_id,
            patient: null,
        ), $data->invoiceNo));
    }

    private function issued(Billing $billing): IssuedInvoice
    {
        return new IssuedInvoice(
            billingId: $billing->id,
            invoiceNo: $billing->invoice_no,
            patientId: $billing->patient->id,
            patientNo: $billing->patient->patient_no,
            amount: $billing->amount,
            billedAt: $billing->billed_at->toImmutable(),
        );
    }
}
