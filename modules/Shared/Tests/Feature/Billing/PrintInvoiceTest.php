<?php

use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Enums\BillingPrintDocumentType;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Billing\Models\BillingItem;
use Modules\Shared\Billing\Models\BillingReferral;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Printing\Models\PrintLog;
use Modules\Shared\Rbac\Enums\SharedPermission;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->cashier = User::factory()->create();
    grantInBranch($this->cashier, $this->branch, SharedPermission::BillingPrint);

    $this->billing = Billing::factory()->create(['branch_id' => $this->branch->id, 'subtotal_amount' => 50000, 'amount' => 50000]);
    BillingItem::factory()->create(['billing_id' => $this->billing->id]);
    BillingReferral::factory()->create(['billing_id' => $this->billing->id]);

    $this->print = fn (array $payload = []) => signInToBranch($this, $this->cashier, $this->branch)
        ->postJson("/api/billing/invoices/{$this->billing->id}/print", $payload);
});

it('prints the bill as a PDF and logs it, a reprint needing a reason', function () {
    ($this->print)()->assertOk()->assertHeader('Content-Type', 'application/pdf');

    ($this->print)()->assertUnprocessable()->assertJson(['code' => 'REPRINT_REASON_REQUIRED']);

    ($this->print)(['reprint_reason' => 'Patient lost the copy'])->assertOk();

    expect(PrintLog::where('document_type', BillingPrintDocumentType::Invoice->value)->pluck('is_reprint')->all())->toBe([false, true]);
});

it('does not print another branch\'s bill', function () {
    $this->billing->update(['branch_id' => Branch::factory()->create()->id]);

    ($this->print)()->assertForbidden();
});
