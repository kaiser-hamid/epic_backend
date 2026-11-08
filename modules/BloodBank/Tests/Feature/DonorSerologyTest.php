<?php

use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Enums\BloodBankPermission;
use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Enums\SampleStatus;
use Modules\BloodBank\Enums\SampleSubject;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\Donor;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Sample;
use Modules\BloodBank\Models\TestResult;
use Modules\Shared\Audit\Models\AuditLog;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Patient\Enums\BloodGroup;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->physician = User::factory()->create(['name' => 'Dr. Nasrin']);
    grantInBranch($this->physician, $this->branch, BloodBankPermission::SerologyView, BloodBankPermission::SerologyRecord);

    $this->case = PatientCase::factory()->create([
        'branch_id' => $this->branch->id,
        'billing_id' => Billing::factory()->create(['invoice_no' => 'NB2611020001'])->id,
    ]);
    $this->donor = CaseDonor::factory()->create([
        'case_id' => $this->case->id,
        'donor_id' => Donor::factory()->create(['name' => 'Karim Mia', 'blood_group' => null]),
        'status' => CaseDonorStatus::Fit,
    ]);
    $this->sample = Sample::factory()->create([
        'sample_no' => 'S2611020001',
        'case_id' => $this->case->id,
        'case_donor_id' => $this->donor->id,
        'subject_type' => SampleSubject::Donor,
    ]);

    $this->serology = [
        'blood_group' => 'B_POS',
        'hbsag' => 'non_reactive',
        'anti_hcv' => 'non_reactive',
        'anti_hiv' => 'non_reactive',
        'syphilis' => 'non_reactive',
        'malarial_parasite' => 'not_found',
        'decision' => 'accepted',
    ];

    $this->record = fn (array $payload, ?CaseDonor $caseDonor = null) => signInToBranch($this, $this->physician, $this->branch)
        ->putJson(sprintf('/api/blood-bank/cases/%d/donors/%d/serology', $this->case->id, ($caseDonor ?? $this->donor)->id), $payload);
});

it('records the results, makes the donor eligible and sets the donor\'s blood group', function () {
    ($this->record)($this->serology)
        ->assertOk()
        ->assertJsonPath('data.donors.0.case_donor_status', 'eligible')
        ->assertJsonPath('data.donors.0.decision', 'accepted')
        ->assertJsonPath('data.donors.0.blood_group', 'B_POS')
        ->assertJsonPath('data.donors.0.sample.sample_no', 'S2611020001')
        ->assertJsonPath('data.donors.0.serology.anti_hiv', 'non_reactive')
        ->assertJsonPath('data.donors.0.serology.malarial_parasite', 'not_found')
        ->assertJsonPath('data.donors.0.serology.version', 1)
        ->assertJsonPath('data.donors.0.serology.tested_by.name', 'Dr. Nasrin')
        ->assertJsonPath('data.donors.0.can_record', true);

    expect(TestResult::where('sample_id', $this->sample->id)->current()->count())->toBe(6)
        ->and($this->sample->refresh()->status)->toBe(SampleStatus::Tested)
        ->and($this->donor->donor->refresh()->blood_group)->toBe(BloodGroup::BPositive)
        ->and(AuditLog::where('action', BloodBankAuditAction::DonorSerologyRecorded)->count())->toBe(1);
});

it('refuses to accept a donor with a reactive or malaria found result but allows the deferral', function () {
    ($this->record)([...$this->serology, 'hbsag' => 'reactive'])
        ->assertUnprocessable()
        ->assertJson(['code' => 'REACTIVE_DONOR_NOT_ACCEPTABLE']);

    ($this->record)([...$this->serology, 'malarial_parasite' => 'found'])
        ->assertUnprocessable()
        ->assertJson(['code' => 'REACTIVE_DONOR_NOT_ACCEPTABLE']);

    expect(TestResult::count())->toBe(0);

    ($this->record)([...$this->serology, 'hbsag' => 'reactive', 'decision' => 'deferred', 'deferral_reason' => 'HBsAg reactive'])
        ->assertOk()
        ->assertJsonPath('data.donors.0.case_donor_status', 'deferred')
        ->assertJsonPath('data.donors.0.decision', 'deferred')
        ->assertJsonPath('data.donors.0.deferral_reason', 'HBsAg reactive');
});

it('keeps the old result as history when a result is amended, with a reason', function () {
    ($this->record)($this->serology)->assertOk();

    ($this->record)([...$this->serology, 'blood_group' => 'O_POS'])
        ->assertUnprocessable()
        ->assertJson(['code' => 'AMENDMENT_REASON_REQUIRED']);

    ($this->record)([...$this->serology, 'blood_group' => 'O_POS', 'amendment_reason' => 'Re-tested, typing error'])
        ->assertOk()
        ->assertJsonPath('data.donors.0.serology.blood_group', 'O_POS')
        ->assertJsonPath('data.donors.0.serology.version', 2);

    $history = TestResult::where('sample_id', $this->sample->id)->orderBy('id')->get();
    $bloodGroup = $history->where('result_value', 'O_POS')->sole();

    expect($history)->toHaveCount(7)
        ->and($history->where('result_value', 'B_POS')->sole()->is_current)->toBeFalse()
        ->and($bloodGroup->version)->toBe(2)
        ->and($bloodGroup->amendment_reason)->toBe('Re-tested, typing error')
        ->and($this->donor->donor->refresh()->blood_group)->toBe(BloodGroup::OPositive)
        ->and(AuditLog::where('action', BloodBankAuditAction::DonorSerologyAmended)->sole()->old_values)
        ->toMatchArray(['blood_group' => 'B_POS', 'status' => 'eligible']);
});

it('lets the physician switch a serology deferral to an acceptance without an amendment reason', function () {
    ($this->record)([...$this->serology, 'decision' => 'deferred', 'deferral_reason' => 'Doubtful result'])->assertOk();

    ($this->record)($this->serology)
        ->assertOk()
        ->assertJsonPath('data.donors.0.case_donor_status', 'eligible')
        ->assertJsonPath('data.donors.0.deferral_reason', null);

    expect(TestResult::count())->toBe(6);
});

it('refuses the form while the donor cannot be tested', function (Closure $setUp, string $code, int $status) {
    $setUp($this);

    ($this->record)($this->serology)
        ->assertStatus($status)
        ->assertJson(['code' => $code]);

    expect(TestResult::count())->toBe(0);
})->with([
    'not accepted at the physical exam yet' => [fn ($test) => $test->donor->update(['status' => CaseDonorStatus::Registered]), 'DONOR_NOT_FIT', 422],
    'deferred at the physical exam' => [fn ($test) => $test->donor->update(['status' => CaseDonorStatus::Deferred]), 'DONOR_DEFERRED', 422],
    'sample rejected' => [fn ($test) => $test->sample->update(['status' => SampleStatus::Rejected]), 'DONOR_SAMPLE_NOT_COLLECTED', 422],
    'bag already collected' => [fn ($test) => $test->donor->update(['status' => CaseDonorStatus::Collected]), 'SEROLOGY_LOCKED', 409],
]);

it('rejects an incomplete or inconsistent form', function (array $override, string $field) {
    ($this->record)([...$this->serology, ...$override])
        ->assertUnprocessable()
        ->assertJson(['code' => 'VALIDATION_FAILED'])
        ->assertJsonValidationErrors($field);
})->with([
    'missing blood group' => [['blood_group' => null], 'blood_group'],
    'unknown result' => [['syphilis' => 'positive'], 'syphilis'],
    'missing malarial parasite' => [['malarial_parasite' => null], 'malarial_parasite'],
    'reactive is not a malarial parasite result' => [['malarial_parasite' => 'reactive'], 'malarial_parasite'],
    'decision of another stage' => [['decision' => 'fit'], 'decision'],
    'deferral without a reason' => [['decision' => 'deferred'], 'deferral_reason'],
    'reason on an acceptance' => [['deferral_reason' => 'Tattoo'], 'deferral_reason'],
]);

it('shows only the scanned donor sample\'s donor, and every donor for an invoice scan', function () {
    $second = CaseDonor::factory()->create(['case_id' => $this->case->id, 'sequence_no' => 2, 'status' => CaseDonorStatus::Fit]);
    Sample::factory()->create([
        'sample_no' => 'S2611020002',
        'case_id' => $this->case->id,
        'case_donor_id' => $second->id,
        'subject_type' => SampleSubject::Donor,
    ]);

    signInToBranch($this, $this->physician, $this->branch)
        ->getJson('/api/blood-bank/donor-serology?sample_no=S2611020002')
        ->assertOk()
        ->assertJsonPath('data.case.id', $this->case->id)
        ->assertJsonPath('data.invoice.invoice_no', 'NB2611020001')
        ->assertJsonCount(1, 'data.donors')
        ->assertJsonPath('data.donors.0.case_donor_id', $second->id)
        ->assertJsonPath('data.donors.0.sample.sample_no', 'S2611020002');

    signInToBranch($this, $this->physician, $this->branch)
        ->getJson('/api/blood-bank/donor-serology?invoice_no=NB2611020001')
        ->assertOk()
        ->assertJsonCount(2, 'data.donors');
});

it('does not open a case from a patient sample or another branch\'s sample', function (Closure $sample) {
    signInToBranch($this, $this->physician, $this->branch)
        ->getJson('/api/blood-bank/donor-serology?sample_no='.$sample($this)->sample_no)
        ->assertNotFound()
        ->assertJson(['code' => 'DONOR_SAMPLE_NOT_FOUND']);
})->with([
    'patient sample' => [fn ($test) => Sample::factory()->create(['case_id' => $test->case->id, 'subject_type' => SampleSubject::Patient])],
    'another branch' => [fn ($test) => Sample::factory()->create(['case_donor_id' => CaseDonor::factory(), 'subject_type' => SampleSubject::Donor])],
]);

it('lists open cases with an accepted donor whose sample awaits serology as pending', function () {
    $awaitingSample = PatientCase::factory()->create(['branch_id' => $this->branch->id]);
    CaseDonor::factory()->create(['case_id' => $awaitingSample->id, 'status' => CaseDonorStatus::Fit]);
    $decided = PatientCase::factory()->create(['branch_id' => $this->branch->id]);
    $eligible = CaseDonor::factory()->create(['case_id' => $decided->id, 'status' => CaseDonorStatus::Eligible]);
    Sample::factory()->create(['case_id' => $decided->id, 'case_donor_id' => $eligible->id, 'subject_type' => SampleSubject::Donor]);

    $ids = signInToBranch($this, $this->physician, $this->branch)
        ->getJson('/api/blood-bank/donor-serology/pending')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$this->case->id]);
});
