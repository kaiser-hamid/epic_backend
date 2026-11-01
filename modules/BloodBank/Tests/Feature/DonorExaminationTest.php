<?php

use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Enums\BloodBankPermission;
use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Enums\SampleStatus;
use Modules\BloodBank\Enums\SampleSubject;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\Donor;
use Modules\BloodBank\Models\DonorScreening;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Sample;
use Modules\Shared\Audit\Models\AuditLog;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Branch\Models\Branch;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->physician = User::factory()->create(['name' => 'Dr. Nasrin']);
    grantInBranch($this->physician, $this->branch, BloodBankPermission::DonorExamView, BloodBankPermission::DonorExamRecord);

    $this->case = PatientCase::factory()->create([
        'branch_id' => $this->branch->id,
        'billing_id' => Billing::factory()->create(['invoice_no' => 'NB2611010001'])->id,
    ]);
    $this->donor = CaseDonor::factory()->create([
        'case_id' => $this->case->id,
        'donor_id' => Donor::factory()->create(['name' => 'Karim Mia']),
    ]);

    $this->examination = [
        'weight_kg' => 68.5,
        'height_cm' => 170.2,
        'hemoglobin_g_dl' => 13.8,
        'pulse_bpm' => 76,
        'bp_systolic' => 120,
        'bp_diastolic' => 80,
        'temperature_f' => 98.4,
        'decision' => 'accepted',
    ];

    $this->examine = fn (array $payload, ?CaseDonor $caseDonor = null, ?PatientCase $case = null) => signInToBranch($this, $this->physician, $this->branch)
        ->putJson(sprintf(
            '/api/blood-bank/cases/%d/donors/%d/physical-examination',
            ($case ?? $this->case)->id,
            ($caseDonor ?? $this->donor)->id,
        ), $payload);
});

it('records the physical examination and the physician\'s acceptance', function () {
    ($this->examine)([...$this->examination, 'decision' => 'accepted'])
        ->assertOk()
        ->assertJsonPath('data.donors.0.name', 'Karim Mia')
        ->assertJsonPath('data.donors.0.case_donor_status', 'fit')
        ->assertJsonPath('data.donors.0.decision', 'accepted')
        ->assertJsonPath('data.donors.0.assessed_by.name', 'Dr. Nasrin')
        ->assertJsonPath('data.donors.0.examination.hemoglobin_g_dl', 13.8)
        ->assertJsonPath('data.donors.0.examination.bp_systolic', 120)
        ->assertJsonPath('data.donors.0.examination.examined_by.name', 'Dr. Nasrin')
        ->assertJsonPath('data.donors.0.can_examine', true);

    $screening = DonorScreening::sole();
    expect($screening->case_donor_id)->toBe($this->donor->id)
        ->and($screening->pre_bp_diastolic)->toBe(80)
        ->and($screening->post_pulse_bpm)->toBeNull()
        ->and($this->donor->refresh()->status)->toBe(CaseDonorStatus::Fit)
        ->and(AuditLog::where('action', BloodBankAuditAction::DonorExamined)->count())->toBe(1);
});

it('audits a correction with the old and new values', function () {
    ($this->examine)([...$this->examination, 'decision' => 'accepted'])->assertOk();

    ($this->examine)([...$this->examination, 'hemoglobin_g_dl' => 11.2, 'decision' => 'deferred', 'deferral_reason' => 'Low haemoglobin'])
        ->assertOk()
        ->assertJsonPath('data.donors.0.decision', 'deferred')
        ->assertJsonPath('data.donors.0.deferral_reason', 'Low haemoglobin');

    $audit = AuditLog::where('action', BloodBankAuditAction::DonorExaminationAmended)->sole();
    expect($audit->old_values)->toMatchArray(['hemoglobin_g_dl' => '13.8', 'status' => 'fit'])
        ->and($audit->new_values)->toMatchArray(['hemoglobin_g_dl' => '11.2', 'status' => 'deferred', 'deferral_reason' => 'Low haemoglobin'])
        ->and(DonorScreening::count())->toBe(1);
});

it('rejects an implausible or incomplete form', function (array $override, string $field) {
    ($this->examine)([...$this->examination, ...$override])
        ->assertUnprocessable()
        ->assertJson(['code' => 'VALIDATION_FAILED'])
        ->assertJsonValidationErrors($field);

    expect(DonorScreening::count())->toBe(0);
})->with([
    'missing haemoglobin' => [['hemoglobin_g_dl' => null], 'hemoglobin_g_dl'],
    'missing decision' => [['decision' => null], 'decision'],
    'decision of another stage' => [['decision' => 'eligible'], 'decision'],
    'diastolic not below systolic' => [['bp_systolic' => 90, 'bp_diastolic' => 90], 'bp_diastolic'],
    'weight with three decimals' => [['weight_kg' => 68.555], 'weight_kg'],
    'deferral without a reason' => [['decision' => 'deferred'], 'deferral_reason'],
    'reason on an acceptance' => [['decision' => 'accepted', 'deferral_reason' => 'Tattoo'], 'deferral_reason'],
]);

it('accepts the form without a height', function () {
    ($this->examine)([...$this->examination, 'height_cm' => null])
        ->assertOk()
        ->assertJsonPath('data.donors.0.examination.height_cm', null);
});

it('refuses the form once the donor sample has been drawn', function () {
    Sample::factory()->create(['case_id' => $this->case->id, 'case_donor_id' => $this->donor->id, 'subject_type' => SampleSubject::Donor]);

    ($this->examine)($this->examination)
        ->assertConflict()
        ->assertJson(['code' => 'EXAMINATION_LOCKED']);

    signInToBranch($this, $this->physician, $this->branch)
        ->getJson("/api/blood-bank/cases/{$this->case->id}/donor-examination")
        ->assertJsonPath('data.donors.0.can_examine', false);
});

it('allows the form again after the donor sample was rejected', function () {
    Sample::factory()->create([
        'case_id' => $this->case->id,
        'case_donor_id' => $this->donor->id,
        'subject_type' => SampleSubject::Donor,
        'status' => SampleStatus::Rejected,
    ]);

    ($this->examine)($this->examination)->assertOk();
});

it('refuses the form for a closed case or a donor of another case', function (Closure $setUp, string $code) {
    [$case, $caseDonor] = $setUp($this);

    ($this->examine)($this->examination, $caseDonor, $case)
        ->assertUnprocessable()
        ->assertJson(['code' => $code]);

    expect(DonorScreening::count())->toBe(0);
})->with([
    'closed case' => [function ($test) {
        $test->case->update(['status' => CaseStatus::Closed]);

        return [$test->case, $test->donor];
    }, 'CASE_NOT_OPEN'],
    'donor of another case' => [fn ($test) => [$test->case, CaseDonor::factory()->create()], 'CASE_DONOR_NOT_IN_CASE'],
]);

it('returns 403 FORBIDDEN for another branch\'s case', function () {
    $foreign = PatientCase::factory()->create();
    $foreignDonor = CaseDonor::factory()->create(['case_id' => $foreign->id]);

    ($this->examine)($this->examination, $foreignDonor, $foreign)
        ->assertForbidden()
        ->assertJson(['code' => 'FORBIDDEN']);
});

it('opens the case from a replacement donor\'s invoice', function () {
    $replacement = CaseDonor::factory()->create([
        'case_id' => $this->case->id,
        'sequence_no' => 2,
        'billing_id' => Billing::factory()->create(['invoice_no' => 'NB2611010002'])->id,
    ]);

    signInToBranch($this, $this->physician, $this->branch)
        ->getJson('/api/blood-bank/donor-examination?invoice_no=NB2611010002')
        ->assertOk()
        ->assertJsonPath('data.case.id', $this->case->id)
        ->assertJsonPath('data.invoice.invoice_no', 'NB2611010001')
        ->assertJsonPath('data.donors.0.case_donor_id', $this->donor->id)
        ->assertJsonPath('data.donors.1.case_donor_id', $replacement->id);
});

it('lists open cases with a donor not yet accepted or deferred as pending', function () {
    $decided = PatientCase::factory()->create(['branch_id' => $this->branch->id]);
    CaseDonor::factory()->create(['case_id' => $decided->id, 'status' => CaseDonorStatus::Fit]);
    CaseDonor::factory()->create(['case_id' => $decided->id, 'sequence_no' => 2, 'status' => CaseDonorStatus::Deferred]);
    PatientCase::factory()->create(['branch_id' => $this->branch->id]);
    $closed = PatientCase::factory()->create(['branch_id' => $this->branch->id, 'status' => CaseStatus::Closed]);
    CaseDonor::factory()->create(['case_id' => $closed->id]);

    $ids = signInToBranch($this, $this->physician, $this->branch)
        ->getJson('/api/blood-bank/donor-examination/pending')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$this->case->id]);
});
