<?php

use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Enums\BillingPrintDocumentType;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Billing\Models\BillingItem;
use Modules\Shared\Billing\Models\BillingReferral;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Printing\Contracts\PdfRenderer;
use Modules\Shared\Printing\Models\PrintLog;
use Modules\Shared\Rbac\Enums\SharedPermission;
use Modules\Shared\TestCatalog\Models\Department;
use Modules\Shared\TestCatalog\Models\TestItem;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->cashier = User::factory()->create();
    grantInBranch($this->cashier, $this->branch, SharedPermission::BillingPrint, SharedPermission::BillingView);

    $this->billing = Billing::factory()->create([
        'branch_id' => $this->branch->id, 'subtotal_amount' => 50000, 'amount' => 50000, 'paid_amount' => 50000,
    ]);
    BillingItem::factory()->create(['billing_id' => $this->billing->id]);
    BillingReferral::factory()->create(['billing_id' => $this->billing->id]);

    $this->print = fn (array $payload = []) => signInToBranch($this, $this->cashier, $this->branch)
        ->postJson("/api/billing/invoices/{$this->billing->id}/print", $payload);
});

/**
 * Swaps the PDF renderer for one that keeps the data each render was given.
 */
function captureRenders(): ArrayObject
{
    $renders = new ArrayObject;
    $renderer = Mockery::mock(PdfRenderer::class);
    $renderer->shouldReceive('render')->andReturnUsing(function (string $view, array $data) use ($renders): string {
        $renders[] = $data;

        return '%PDF-1.4';
    });
    app()->instance(PdfRenderer::class, $renderer);

    return $renders;
}

it('prints the bill as a PDF and logs it, a reprint needing a reason', function () {
    ($this->print)()->assertOk()->assertHeader('Content-Type', 'application/pdf');

    ($this->print)()->assertUnprocessable()->assertJson(['code' => 'REPRINT_REASON_REQUIRED']);

    ($this->print)(['reprint_reason' => 'Patient lost the copy'])->assertOk();

    expect(PrintLog::where('document_type', BillingPrintDocumentType::Invoice->value)->pluck('is_reprint')->all())->toBe([false, true]);
});

it('prints the Customer Copy with prices and one Non Lab Copy per department, Original first and Duplicate after', function () {
    $renders = captureRenders();
    $bloodBank = Department::factory()->create(['name' => 'Blood Bank', 'room_no' => '900']);
    $hormone = Department::factory()->create(['name' => 'Hormone', 'room_no' => null]);
    $this->billing->items()->delete();
    $this->billing->update(['subtotal_amount' => 300000, 'amount' => 300000, 'paid_amount' => 200000]);
    foreach ([[$bloodBank, 100000], [$hormone, 120000], [$bloodBank, 80000]] as [$department, $amount]) {
        BillingItem::factory()->create([
            'billing_id' => $this->billing->id,
            'test_item_id' => TestItem::factory()->create(['department_id' => $department->id])->id,
            'amount' => $amount,
        ]);
    }

    ($this->print)()->assertOk();
    ($this->print)(['reprint_reason' => 'Patient lost the copy'])->assertOk();

    [$original, $duplicate] = $renders;
    expect($original['header']['copy_status'])->toBe('Original Copy')
        ->and($duplicate['header']['copy_status'])->toBe('Duplicate Copy')
        ->and($original['header']['is_paid'])->toBeFalse()
        ->and($original['customerCopy']['items'])->toHaveCount(3)
        ->and($original['customerCopy']['items'][0]['price'])->toBe('1,000.00')
        ->and($original['customerCopy']['due'])->toBe('1,000.00')
        ->and($original['customerCopy']['in_words'])->toBe('Taka Three Thousand Only')
        ->and(array_column($original['departmentCopies'], 'department'))->toBe(['Blood Bank (Room No. 900)', 'Hormone'])
        ->and($original['departmentCopies'][0]['items'])->toHaveCount(2)
        ->and($original['departmentCopies'][0]['items'][0]['price'])->toBeNull();
});

it('previews the bill without logging a print', function () {
    $renders = captureRenders();

    signInToBranch($this, $this->cashier, $this->branch)
        ->getJson("/api/billing/invoices/{$this->billing->id}/preview")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($renders[0]['header']['copy_status'])->toBe('Preview')
        ->and(PrintLog::count())->toBe(0);
});

it('does not print another branch\'s bill', function () {
    $this->billing->update(['branch_id' => Branch::factory()->create()->id]);

    ($this->print)()->assertForbidden();
});
