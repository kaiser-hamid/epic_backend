<?php

use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Enums\BloodBankPermission;
use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Enums\CollectionStatus;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\Collection;
use Modules\BloodBank\Models\Donor;
use Modules\BloodBank\Models\PatientCase;
use Modules\Shared\Audit\Models\AuditLog;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Patient\Models\PatientMaster;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->phlebotomist = User::factory()->create(['name' => 'Salma Akter']);
    grantInBranch(
        $this->phlebotomist,
        $this->branch,
        BloodBankPermission::CollectionView,
        BloodBankPermission::CollectionCollect,
        BloodBankPermission::CollectionLabelPrint,
    );

    $this->case = PatientCase::factory()->create([
        'branch_id' => $this->branch->id,
        'billing_id' => Billing::factory()->create(['invoice_no' => 'NB2611020001'])->id,
        'patient_master_id' => PatientMaster::factory()->create(['name' => 'Rahim Uddin', 'patient_no' => 'P2611020001'])->id,
    ]);
    $this->donor = CaseDonor::factory()->create([
        'case_id' => $this->case->id,
        'donor_id' => Donor::factory()->create(['name' => 'Karim Mia', 'donor_no' => 'D2611000001']),
        'sequence_no' => 1,
        'status' => CaseDonorStatus::Eligible,
    ]);

    $this->collect = fn (?CaseDonor $caseDonor = null, array $payload = ['volume_ml' => 450], ?PatientCase $case = null) => signInToBranch($this, $this->phlebotomist, $this->branch)
        ->postJson(sprintf('/api/blood-bank/cases/%d/donors/%d/collection', ($case ?? $this->case)->id, ($caseDonor ?? $this->donor)->id), $payload);
});

it('collects blood from an eligible donor into a bag with a daily bag number', function () {
    $this->travelTo(now()->setDate(2026, 11, 2));

    ($this->collect)(payload: ['volume_ml' => 450, 'notes' => 'Smooth donation'])
        ->assertCreated()
        ->assertJsonPath('data.donors.0.case_donor_status', 'collected')
        ->assertJsonPath('data.donors.0.is_collectable', false)
        ->assertJsonPath('data.donors.0.collection.bag_no', 'B2611020001')
        ->assertJsonPath('data.donors.0.collection.volume_ml', 450)
        ->assertJsonPath('data.donors.0.collection.collected_by.name', 'Salma Akter')
        ->assertJsonPath('data.donors.0.collection.label_print_count', 0);

    $collection = Collection::sole();

    expect($collection->case_donor_id)->toBe($this->donor->id)
        ->and($collection->status)->toBe(CollectionStatus::Collected)
        ->and($collection->notes)->toBe('Smooth donation')
        ->and($this->donor->refresh()->status)->toBe(CaseDonorStatus::Collected)
        ->and(AuditLog::where('action', BloodBankAuditAction::BloodCollected)->sole()->new_values)
        ->toMatchArray(['bag_no' => 'B2611020001', 'donor_no' => 'D2611000001', 'case_no' => $this->case->case_no]);

    ($this->collect)()->assertStatus(409)->assertJson(['code' => 'BAG_ALREADY_COLLECTED']);

    expect(Collection::count())->toBe(1);
});

it('refuses a donor who is not cleared to donate', function (CaseDonorStatus $status, string $code) {
    $this->donor->update(['status' => $status]);

    ($this->collect)()->assertUnprocessable()->assertJson(['code' => $code]);

    expect(Collection::count())->toBe(0);
})->with([
    'serology pending' => [CaseDonorStatus::Fit, 'DONOR_NOT_ELIGIBLE'],
    'physical exam pending' => [CaseDonorStatus::Registered, 'DONOR_NOT_FIT'],
    'deferred' => [CaseDonorStatus::Deferred, 'DONOR_DEFERRED'],
]);

it('refuses a closed case and another branch\'s case', function () {
    $this->case->update(['status' => CaseStatus::Closed]);

    ($this->collect)()->assertUnprocessable()->assertJson(['code' => 'CASE_NOT_OPEN']);

    $otherCase = PatientCase::factory()->create();
    ($this->collect)(CaseDonor::factory()->create(['case_id' => $otherCase->id, 'status' => CaseDonorStatus::Eligible]), case: $otherCase)
        ->assertForbidden()
        ->assertJson(['code' => 'FORBIDDEN']);
});

it('rejects a missing or implausible volume', function (array $payload) {
    ($this->collect)(payload: $payload)
        ->assertUnprocessable()
        ->assertJson(['code' => 'VALIDATION_FAILED'])
        ->assertJsonValidationErrors('volume_ml');
})->with([
    'missing' => [[]],
    'too much' => [['volume_ml' => 900]],
]);

it('opens the donor\'s open case of this branch for a scanned donor no, showing only that donor', function () {
    CaseDonor::factory()->create(['case_id' => $this->case->id, 'sequence_no' => 2, 'status' => CaseDonorStatus::Eligible]);
    $lookup = fn (string $donorNo) => signInToBranch($this, $this->phlebotomist, $this->branch)
        ->getJson("/api/blood-bank/blood-collection?donor_no={$donorNo}");

    $lookup('D2611000001')
        ->assertOk()
        ->assertJsonPath('data.case.id', $this->case->id)
        ->assertJsonPath('data.patient.name', 'Rahim Uddin')
        ->assertJsonCount(1, 'data.donors')
        ->assertJsonPath('data.donors.0.case_donor_id', $this->donor->id)
        ->assertJsonPath('data.donors.0.is_collectable', true)
        ->assertJsonPath('data.donors.0.collection', null);

    $this->case->update(['branch_id' => Branch::factory()->create()->id]);

    $lookup('D2611000001')->assertNotFound()->assertJson(['code' => 'CASE_NOT_FOUND']);
});

it('prints the bag label with donor, case and patient details and requires a reason for a reprint', function () {
    $collection = Collection::factory()->create(['case_donor_id' => $this->donor->id, 'bag_no' => 'B2611020001']);
    $print = fn (array $payload = []) => signInToBranch($this, $this->phlebotomist, $this->branch)
        ->postJson("/api/blood-bank/cases/{$this->case->id}/bag-labels", ['collection_ids' => [$collection->id], ...$payload]);

    $response = $print()
        ->assertOk()
        ->assertJsonPath('data.0.bag_no', 'B2611020001')
        ->assertJsonPath('data.0.donor.donor_no', 'D2611000001')
        ->assertJsonPath('data.0.donor.name', 'Karim Mia')
        ->assertJsonPath('data.0.case.case_no', $this->case->case_no)
        ->assertJsonPath('data.0.patient.patient_no', 'P2611020001')
        ->assertJsonPath('data.0.patient.name', 'Rahim Uddin')
        ->assertJsonPath('data.0.label_print.is_reprint', false);

    expect($response->json('data.0.label_command'))
        ->toContain('BA3,42,63,2,5,64,0,3,B2611020001
')
        ->toContain(',0E,Rahim Uddin
')
        ->toContain(',0E,Karim Mia
')
        ->toContain(',0E,WB
')
        ->toContain(',0E,NB2611020001
');

    $print()->assertUnprocessable()->assertJson(['code' => 'REPRINT_REASON_REQUIRED']);

    $print(['reprint_reason' => 'Label torn'])
        ->assertOk()
        ->assertJsonPath('data.0.label_print.is_reprint', true);
});

it('does not print a label for a bag of another case', function () {
    signInToBranch($this, $this->phlebotomist, $this->branch)
        ->postJson("/api/blood-bank/cases/{$this->case->id}/bag-labels", ['collection_ids' => [Collection::factory()->create()->id]])
        ->assertUnprocessable()
        ->assertJson(['code' => 'COLLECTION_NOT_IN_CASE']);
});

it('lists open cases with a donor cleared to donate as pending', function () {
    $waitingForSerology = PatientCase::factory()->create(['branch_id' => $this->branch->id]);
    CaseDonor::factory()->create(['case_id' => $waitingForSerology->id, 'status' => CaseDonorStatus::Fit]);
    $alreadyCollected = PatientCase::factory()->create(['branch_id' => $this->branch->id]);
    CaseDonor::factory()->create(['case_id' => $alreadyCollected->id, 'status' => CaseDonorStatus::Collected]);

    signInToBranch($this, $this->phlebotomist, $this->branch)
        ->getJson('/api/blood-bank/blood-collection/pending')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->case->id);
});

it('finds a collected bag by its barcode on the collected tab', function () {
    Collection::factory()->create(['case_donor_id' => $this->donor->id, 'bag_no' => 'B2611020001']);
    Collection::factory()->create([
        'case_donor_id' => CaseDonor::factory()->create(['case_id' => $this->case->id, 'sequence_no' => 2]),
        'bag_no' => 'B2611020002',
    ]);

    signInToBranch($this, $this->phlebotomist, $this->branch)
        ->getJson('/api/blood-bank/blood-collections?filter[bag_no]=B2611020001')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.bag_no', 'B2611020001')
        ->assertJsonPath('data.0.donor.name', 'Karim Mia')
        ->assertJsonPath('data.0.patient.name', 'Rahim Uddin');
});
