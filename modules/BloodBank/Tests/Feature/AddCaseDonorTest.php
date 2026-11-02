<?php

use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Enums\BloodBankPermission;
use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\Donor;
use Modules\BloodBank\Models\PatientCase;
use Modules\Shared\Audit\Models\AuditLog;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Billing\Models\BillingReferral;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Doctor\Models\Doctor;
use Modules\Shared\TestCatalog\Models\TestItem;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->executive = User::factory()->create();
    grantInBranch($this->executive, $this->branch, BloodBankPermission::CaseCreate);

    $this->caseBill = Billing::factory()->create(['invoice_no' => 'NB2611020001', 'branch_id' => $this->branch->id]);
    $this->consultant = Doctor::factory()->create();
    BillingReferral::factory()->create(['billing_id' => $this->caseBill->id, 'doctor_id' => $this->consultant->id]);

    $this->case = PatientCase::factory()->create([
        'branch_id' => $this->branch->id,
        'patient_master_id' => $this->caseBill->patient_master_id,
        'billing_id' => $this->caseBill->id,
    ]);
    $this->firstDonor = CaseDonor::factory()->create(['case_id' => $this->case->id, 'status' => CaseDonorStatus::Deferred]);

    $this->addDonor = fn (array $payload, ?PatientCase $case = null) => signInToBranch($this, $this->executive, $this->branch)
        ->postJson('/api/blood-bank/cases/'.($case ?? $this->case)->id.'/donors', $payload);
});

it('adds a returning donor with the additional donor charge billed under the vendor invoice number', function () {
    $donor = Donor::factory()->create(['name' => 'Karim Mia']);

    ($this->addDonor)(['invoice_no' => 'NB2611020007', 'donor_id' => $donor->id])
        ->assertCreated()
        ->assertJsonPath('data.id', $this->case->id)
        ->assertJsonPath('data.invoice.invoice_no', 'NB2611020001')
        ->assertJsonPath('data.donors.0.invoice_no', null)
        ->assertJsonPath('data.donors.1.sequence_no', 2)
        ->assertJsonPath('data.donors.1.status', 'registered')
        ->assertJsonPath('data.donors.1.invoice_no', 'NB2611020007')
        ->assertJsonPath('data.donors.1.donor.name', 'Karim Mia');

    $bill = Billing::where('invoice_no', 'NB2611020007')->with(['items', 'referral'])->sole();
    $caseDonor = CaseDonor::where('donor_id', $donor->id)->sole();

    expect($caseDonor->billing_id)->toBe($bill->id)
        ->and($bill->patient_master_id)->toBe($this->case->patient_master_id)
        ->and($bill->branch_id)->toBe($this->branch->id)
        ->and($bill->amount)->toBe(100000)
        ->and($bill->items->sole()->test_item_id)->toBe(TestItem::where('code', 'BB-ADDL-DONOR')->value('id'))
        ->and($bill->items->sole()->rate)->toBe(100000)
        ->and($bill->referral->doctor_id)->toBe($this->consultant->id)
        ->and(AuditLog::where('action', BloodBankAuditAction::CaseDonorAdded)->sole()->new_values)
        ->toMatchArray(['invoice_no' => 'NB2611020007', 'sequence_no' => 2, 'donor_id' => $donor->id]);
});

it('opens the case on the add donor page from the case bill or an added donor\'s bill', function () {
    ($this->addDonor)(['invoice_no' => 'NB2611020007', 'donor_id' => Donor::factory()->create()->id])->assertCreated();

    foreach (['NB2611020001', 'NB2611020007'] as $invoiceNo) {
        signInToBranch($this, $this->executive, $this->branch)
            ->getJson("/api/blood-bank/add-donor?invoice_no={$invoiceNo}")
            ->assertOk()
            ->assertJsonPath('data.id', $this->case->id)
            ->assertJsonPath('data.invoice.invoice_no', 'NB2611020001')
            ->assertJsonCount(2, 'data.donors');
    }
});

it('does not open another branch\'s case on the add donor page', function () {
    PatientCase::factory()->create(['billing_id' => Billing::factory()->create(['invoice_no' => 'NE2611020001'])->id]);

    signInToBranch($this, $this->executive, $this->branch)
        ->getJson('/api/blood-bank/add-donor?invoice_no=NE2611020001')
        ->assertNotFound()
        ->assertJson(['code' => 'CASE_NOT_FOUND']);
});

it('registers a new donor and adds them in the same request', function () {
    ($this->addDonor)(['invoice_no' => 'NB2611020007', 'donor' => ['name' => 'Selim Reza', 'age' => 30, 'phone' => '01811000000']])
        ->assertCreated()
        ->assertJsonPath('data.donors.1.donor.name', 'Selim Reza');

    expect(Donor::where('phone', '01811000000')->sole()->caseDonors()->sole()->case_id)->toBe($this->case->id);
});

it('leaves nothing behind when the invoice number is already used', function () {
    ($this->addDonor)(['invoice_no' => 'NB2611020001', 'donor' => ['name' => 'Selim Reza', 'age' => 30]])
        ->assertUnprocessable()
        ->assertJson(['code' => 'INVOICE_NO_TAKEN']);

    expect(Donor::where('name', 'Selim Reza')->exists())->toBeFalse()
        ->and(Billing::count())->toBe(1)
        ->and(CaseDonor::count())->toBe(1);
});

it('refuses a donor already on the case, even one who was deferred', function () {
    ($this->addDonor)(['invoice_no' => 'NB2611020007', 'donor_id' => $this->firstDonor->donor_id])
        ->assertUnprocessable()
        ->assertJson(['code' => 'DONOR_ALREADY_IN_CASE']);

    expect(Billing::count())->toBe(1);
});

it('refuses a closed case and another branch\'s case', function () {
    $this->case->update(['status' => CaseStatus::Closed]);

    ($this->addDonor)(['invoice_no' => 'NB2611020007', 'donor_id' => Donor::factory()->create()->id])
        ->assertUnprocessable()
        ->assertJson(['code' => 'CASE_NOT_OPEN']);

    ($this->addDonor)(['invoice_no' => 'NB2611020007', 'donor_id' => Donor::factory()->create()->id], PatientCase::factory()->create())
        ->assertForbidden()
        ->assertJson(['code' => 'FORBIDDEN']);
});

it('rejects an incomplete form', function (array $payload, string $field) {
    ($this->addDonor)($payload)
        ->assertUnprocessable()
        ->assertJson(['code' => 'VALIDATION_FAILED'])
        ->assertJsonValidationErrors($field);
})->with([
    'missing invoice no' => [['donor' => ['name' => 'Selim Reza', 'age' => 30]], 'invoice_no'],
    'returning and new donor together' => [fn () => ['invoice_no' => 'NB2611020007', 'donor_id' => Donor::factory()->create()->id, 'donor' => ['name' => 'Selim Reza', 'age' => 30]], 'donor_id'],
]);
