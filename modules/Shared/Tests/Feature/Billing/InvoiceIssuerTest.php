<?php

use Illuminate\Support\Facades\DB;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Contracts\InvoiceIssuer;
use Modules\Shared\Billing\Data\CreateInvoiceData;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Branch\Contracts\CurrentBranch;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Patient\Models\PatientMaster;
use Modules\Shared\TestCatalog\Models\TestItem;

/**
 * Another module's form: a new patient billed for one test.
 */
function newPatientInvoice(): CreateInvoiceData
{
    return CreateInvoiceData::from([
        'patient' => ['name' => 'Rahim Uddin', 'age_years' => 40],
        'items' => [['test_item_id' => TestItem::factory()->create(['rate' => 150000])->id, 'quantity' => 1]],
        'referral' => ['is_self_referral' => true],
    ]);
}

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->actor = User::factory()->create();
    $this->app->instance(CurrentBranch::class, new class($this->branch) implements CurrentBranch
    {
        public function __construct(private readonly Branch $branch) {}

        public function id(): ?int
        {
            return $this->branch->id;
        }

        public function get(): ?Branch
        {
            return $this->branch;
        }
    });
});

it('registers and bills a patient for another module, returning only ids and numbers', function () {
    $this->travelTo(now()->setDate(2026, 10, 28));

    $issued = app(InvoiceIssuer::class)->issue($this->actor, newPatientInvoice());

    $billing = Billing::sole();
    expect($issued->billingId)->toBe($billing->id)
        ->and($issued->invoiceNo)->toBe("{$this->branch->code}2610280001")
        ->and($issued->patientId)->toBe($billing->patient_master_id)
        ->and($issued->patientNo)->toBe('P2610280001')
        ->and($issued->amount)->toBe(150000)
        ->and($billing->branch_id)->toBe($this->branch->id);
});

it('rolls back the patient, the bill and their numbers when the caller\'s transaction fails', function () {
    $this->travelTo(now()->setDate(2026, 10, 28));
    $data = newPatientInvoice();

    expect(fn () => DB::transaction(function () use ($data) {
        app(InvoiceIssuer::class)->issue($this->actor, $data);

        throw new RuntimeException('Blood Bank case could not be created.');
    }))->toThrow(RuntimeException::class);

    expect(Billing::count())->toBe(0)
        ->and(PatientMaster::count())->toBe(0)
        ->and(app(InvoiceIssuer::class)->issue($this->actor, $data)->invoiceNo)->toBe("{$this->branch->code}2610280001");
});
