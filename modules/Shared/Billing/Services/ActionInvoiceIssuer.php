<?php

namespace Modules\Shared\Billing\Services;

use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Actions\CreateInvoice;
use Modules\Shared\Billing\Contracts\InvoiceIssuer;
use Modules\Shared\Billing\Data\CreateInvoiceData;
use Modules\Shared\Billing\Data\IssuedInvoice;

final class ActionInvoiceIssuer implements InvoiceIssuer
{
    public function __construct(private readonly CreateInvoice $createInvoice) {}

    public function issue(User $actor, CreateInvoiceData $data): IssuedInvoice
    {
        $billing = $this->createInvoice->handle($actor, $data);

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
