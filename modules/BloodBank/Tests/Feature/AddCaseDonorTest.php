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
use Modules\Shared\Billing\Enums\BillingStatus;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Branch\Models\Branch;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->executive = User::factory()->create();
    grantInBranch($this->executive, $this->branch, BloodBankPermission::CaseUpdate);

    $this->caseBill = Billing::factory()->bloodBank()->create(['invoice_no' => 'NB2611020001', 'branch_id' => $this->branch->id]);
    $this->case = PatientCase::factory()->create([
        'branch_id' => $this->branch->id,
        'patient_master_id' => $this->caseBill->patient_master_id,
        'billing_id' => $this->caseBill->id,
    ]);
    $this->firstDonor = CaseDonor::factory()->create(['case_id' => $this->case->id, 'status' => CaseDonorStatus::Deferred]);

    $this->donorBill = fn (array $attributes = []) => Billing::factory()->create([
        'invoice_no' => 'NB2611020007',
        'branch_id' => $this->branch->id,
        'patient_master_id' => $this->case->patient_master_id,
        ...$attributes,
    ]);

    $this->addDonor = fn (array $payload, ?PatientCase $case = null) => signInToBranch($this, $this->executive, $this->branch)
        ->postJson('/api/blood-bank/cases/'.($case ?? $this->case)->id.'/donors', $payload);
});

it('adds a returning donor under a bill already saved for the case\'s patient, without billing anything', function () {
    $bill = ($this->donorBill)();
    $donor = Donor::factory()->create(['name' => 'Karim Mia']);

    ($this->addDonor)(['invoice_no' => 'NB2611020007', 'donor_id' => $donor->id])
        ->assertCreated()
        ->assertJsonPath('data.id', $this->case->id)
        ->assertJsonPath('data.invoice.invoice_no', 'NB2611020001')
        ->assertJsonPath('data.donors.1.sequence_no', 2)
        ->assertJsonPath('data.donors.1.status', 'registered')
        ->assertJsonPath('data.donors.1.invoice_no', 'NB2611020007')
        ->assertJsonPath('data.donors.1.donor.name', 'Karim Mia');

    expect(CaseDonor::where('donor_id', $donor->id)->sole()->billing_id)->toBe($bill->id)
        ->and(Billing::count())->toBe(2)
        ->and(AuditLog::where('action', BloodBankAuditAction::CaseDonorAdded)->sole()->new_values)
        ->toMatchArray(['invoice_no' => 'NB2611020007', 'sequence_no' => 2, 'donor_id' => $donor->id]);
});

it('lets several donors of the same case share a bill, including the case\'s own bill', function () {
    ($this->addDonor)(['invoice_no' => 'NB2611020001', 'donor_id' => Donor::factory()->create()->id])->assertCreated();
    ($this->addDonor)(['invoice_no' => 'NB2611020001', 'donor_id' => Donor::factory()->create()->id])
        ->assertCreated()
        ->assertJsonPath('data.donors.2.invoice_no', 'NB2611020001');
});

it('opens the case on the add donor page from the case bill or an added donor\'s bill', function () {
    ($this->donorBill)();
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
    ($this->donorBill)();

    ($this->addDonor)(['invoice_no' => 'NB2611020007', 'donor' => ['name' => 'Selim Reza', 'age' => 30, 'phone' => '01811000000']])
        ->assertCreated()
        ->assertJsonPath('data.donors.1.donor.name', 'Selim Reza');

    expect(Donor::where('phone', '01811000000')->sole()->caseDonors()->sole()->case_id)->toBe($this->case->id);
});

it('refuses a bill that cannot belong to this case and leaves nothing behind', function (Closure $bill, string $code) {
    $bill->call($this);

    ($this->addDonor)(['invoice_no' => 'NB2611020007', 'donor' => ['name' => 'Selim Reza', 'age' => 30]])
        ->assertUnprocessable()
        ->assertJson(['code' => $code]);

    expect(Donor::where('name', 'Selim Reza')->exists())->toBeFalse()
        ->and($this->case->caseDonors()->count())->toBe(1);
})->with([
    'no such bill' => [fn () => null, 'INVOICE_NOT_FOUND'],
    'another branch\'s bill' => [fn () => ($this->donorBill)(['branch_id' => Branch::factory()->create()->id]), 'INVOICE_NOT_IN_BRANCH'],
    'a cancelled bill' => [fn () => ($this->donorBill)(['status' => BillingStatus::Cancelled]), 'INVOICE_NOT_ACTIVE'],
    'another patient\'s bill' => [fn () => Billing::factory()->create(['invoice_no' => 'NB2611020007', 'branch_id' => $this->branch->id]), 'INVOICE_PATIENT_MISMATCH'],
    'the bill of another case' => [fn () => PatientCase::factory()->create([
        'branch_id' => $this->branch->id,
        'patient_master_id' => $this->case->patient_master_id,
        'billing_id' => ($this->donorBill)()->id,
    ]), 'INVOICE_LINKED_TO_OTHER_CASE'],
    'a bill attached to a donor of another case' => [fn () => CaseDonor::factory()->create([
        'case_id' => PatientCase::factory()->create(['branch_id' => $this->branch->id, 'patient_master_id' => $this->case->patient_master_id])->id,
        'billing_id' => ($this->donorBill)()->id,
    ]), 'INVOICE_LINKED_TO_OTHER_CASE'],
]);

it('refuses a donor already on the case, even one who was deferred', function () {
    ($this->donorBill)();

    ($this->addDonor)(['invoice_no' => 'NB2611020007', 'donor_id' => $this->firstDonor->donor_id])
        ->assertUnprocessable()
        ->assertJson(['code' => 'DONOR_ALREADY_IN_CASE']);
});

it('refuses a closed case and another branch\'s case', function () {
    ($this->donorBill)();
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
