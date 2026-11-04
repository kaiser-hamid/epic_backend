<?php

use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Enums\BloodBankPermission;
use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Enums\SampleStatus;
use Modules\BloodBank\Enums\SampleSubject;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\Donor;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Sample;
use Modules\Shared\Audit\Models\AuditLog;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Billing\Models\BillingItem;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Patient\Enums\Gender;
use Modules\Shared\Patient\Models\PatientMaster;
use Modules\Shared\Printing\Models\PrintLog;
use Modules\Shared\TestCatalog\Models\TestItem;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->technologist = User::factory()->create();
    grantInBranch(
        $this->technologist,
        $this->branch,
        BloodBankPermission::SampleView,
        BloodBankPermission::SampleCollect,
        BloodBankPermission::SampleLabelPrint,
    );

    $this->case = PatientCase::factory()->create([
        'branch_id' => $this->branch->id,
        'billing_id' => Billing::factory()->create(['invoice_no' => 'NB2610310001'])->id,
        'patient_master_id' => PatientMaster::factory()->create(['name' => 'Rahim Uddin'])->id,
    ]);
    $this->donor = CaseDonor::factory()->create([
        'case_id' => $this->case->id,
        'donor_id' => Donor::factory()->create(['name' => 'Karim Mia']),
        'sequence_no' => 1,
        'status' => CaseDonorStatus::Fit,
    ]);
});

it('opens the case for a scanned invoice with the patient first, then each donor', function () {
    signInToBranch($this, $this->technologist, $this->branch)
        ->getJson('/api/blood-bank/sample-collection?invoice_no=NB2610310001')
        ->assertOk()
        ->assertJsonPath('data.case.id', $this->case->id)
        ->assertJsonPath('data.patient.name', 'Rahim Uddin')
        ->assertJsonPath('data.invoice.invoice_no', 'NB2610310001')
        ->assertJsonCount(2, 'data.subjects')
        ->assertJsonPath('data.subjects.0.subject_type', 'patient')
        ->assertJsonPath('data.subjects.0.name', 'Rahim Uddin')
        ->assertJsonPath('data.subjects.0.is_collectable', true)
        ->assertJsonPath('data.subjects.0.sample', null)
        ->assertJsonPath('data.subjects.1.subject_type', 'donor')
        ->assertJsonPath('data.subjects.1.case_donor_id', $this->donor->id)
        ->assertJsonPath('data.subjects.1.name', 'Karim Mia');
});

it('returns 404 CASE_NOT_FOUND for an invoice of another branch\'s case', function () {
    $this->case->update(['branch_id' => Branch::factory()->create()->id]);

    signInToBranch($this, $this->technologist, $this->branch)
        ->getJson('/api/blood-bank/sample-collection?invoice_no=NB2610310001')
        ->assertNotFound()
        ->assertJson(['code' => 'CASE_NOT_FOUND']);
});

it('collects the patient and donor samples with daily sample numbers and prints their labels', function () {
    $this->travelTo(now()->setDate(2026, 10, 31)->setTime(20, 16));
    PatientMaster::whereKey($this->case->patient_master_id)->update(['gender' => Gender::Male, 'age_years' => 36]);
    foreach (['BB-WB', 'BB-PCV'] as $code) {
        BillingItem::factory()->create(['billing_id' => $this->case->billing_id, 'test_item_id' => TestItem::factory()->create(['code' => $code])->id]);
    }

    $response = signInToBranch($this, $this->technologist, $this->branch)
        ->postJson("/api/blood-bank/cases/{$this->case->id}/samples", ['subjects' => [
            ['subject_type' => 'patient'],
            ['subject_type' => 'donor', 'case_donor_id' => $this->donor->id],
        ]])
        ->assertCreated()
        ->assertJsonCount(2, 'data.collected_sample_ids')
        ->assertJsonPath('data.subjects.0.sample.sample_no', 'S2610310001')
        ->assertJsonPath('data.subjects.0.sample.label_print_count', 1)
        ->assertJsonPath('data.subjects.0.is_collectable', false)
        ->assertJsonPath('data.subjects.1.sample.sample_no', 'S2610310002');

    expect($response->json('data.subjects.0.sample.label_command'))
        ->toContain(",0,0,S2610310001\r\n")
        ->toContain("AB,32,83,1,1,0,0,2610310001|S2610310001\r\n")
        ->toContain("AB,85,111,1,1,0,0E,BB-WB,BB-PCV\r\n")
        ->toContain("AA,59,170,1,1,0,0E,M-36Y 31/10/2026 08:16 PM\r\n")
        ->and($response->json('data.subjects.1.sample.label_command'))->toContain('|S2610310002')
        ->and(Sample::where('case_donor_id', $this->donor->id)->sole()->subject_type)->toBe(SampleSubject::Donor)
        ->and(AuditLog::where('action', BloodBankAuditAction::SampleCollected)->count())->toBe(2)
        ->and(PrintLog::where('is_reprint', false)->count())->toBe(2);
});

it('collects nothing when one ticked row cannot be collected', function (Closure $setUp, string $code, int $status) {
    $subjects = $setUp($this);
    $samplesBefore = Sample::count();

    signInToBranch($this, $this->technologist, $this->branch)
        ->postJson("/api/blood-bank/cases/{$this->case->id}/samples", ['subjects' => $subjects])
        ->assertStatus($status)
        ->assertJson(['code' => $code]);

    expect(Sample::count())->toBe($samplesBefore);
})->with([
    'patient sample already drawn' => [function ($test) {
        Sample::factory()->create(['case_id' => $test->case->id]);

        return [['subject_type' => 'donor', 'case_donor_id' => $test->donor->id], ['subject_type' => 'patient']];
    }, 'SAMPLE_ALREADY_COLLECTED', 409],
    'deferred donor' => [function ($test) {
        $test->donor->update(['status' => CaseDonorStatus::Deferred]);

        return [['subject_type' => 'patient'], ['subject_type' => 'donor', 'case_donor_id' => $test->donor->id]];
    }, 'DONOR_DEFERRED', 422],
    'donor not yet accepted at the physical exam' => [function ($test) {
        $test->donor->update(['status' => CaseDonorStatus::Registered]);

        return [['subject_type' => 'patient'], ['subject_type' => 'donor', 'case_donor_id' => $test->donor->id]];
    }, 'DONOR_NOT_FIT', 422],
    'donor of another case' => [fn () => [
        ['subject_type' => 'patient'],
        ['subject_type' => 'donor', 'case_donor_id' => CaseDonor::factory()->create()->id],
    ], 'CASE_DONOR_NOT_IN_CASE', 422],
    'closed case' => [function ($test) {
        $test->case->update(['status' => CaseStatus::Closed]);

        return [['subject_type' => 'patient']];
    }, 'CASE_NOT_OPEN', 422],
]);

it('allows a new sample once the previous one was rejected', function () {
    Sample::factory()->create(['case_id' => $this->case->id, 'status' => SampleStatus::Rejected]);

    signInToBranch($this, $this->technologist, $this->branch)
        ->postJson("/api/blood-bank/cases/{$this->case->id}/samples", ['subjects' => [['subject_type' => 'patient']]])
        ->assertCreated()
        ->assertJsonPath('data.subjects.0.sample.status', 'collected');
});

it('prints labels and requires a reason for a reprint', function () {
    $sample = Sample::factory()->create(['case_id' => $this->case->id, 'case_donor_id' => $this->donor->id, 'subject_type' => SampleSubject::Donor]);
    $print = fn (array $payload) => signInToBranch($this, $this->technologist, $this->branch)
        ->postJson("/api/blood-bank/cases/{$this->case->id}/sample-labels", ['sample_ids' => [$sample->id], ...$payload]);

    $print([])
        ->assertOk()
        ->assertJsonPath('data.0.sample_no', $sample->sample_no)
        ->assertJsonPath('data.0.subject.name', 'Karim Mia')
        ->assertJsonPath('data.0.label_print.is_reprint', false)
        ->assertJsonPath('data.0.label_command', fn (string $command): bool => str_contains($command, ",2610310001|{$sample->sample_no}\r\n"));

    $print([])->assertUnprocessable()->assertJson(['code' => 'REPRINT_REASON_REQUIRED']);

    $print(['reprint_reason' => 'Label smudged'])
        ->assertOk()
        ->assertJsonPath('data.0.label_print.is_reprint', true);

    expect(PrintLog::count())->toBe(2);
});

it('does not print a label for a sample of another case', function () {
    signInToBranch($this, $this->technologist, $this->branch)
        ->postJson("/api/blood-bank/cases/{$this->case->id}/sample-labels", ['sample_ids' => [Sample::factory()->create()->id]])
        ->assertUnprocessable()
        ->assertJson(['code' => 'SAMPLE_NOT_IN_CASE']);
});

it('lists open cases still waiting for a sample as pending', function () {
    Sample::factory()->create(['case_id' => $this->case->id]);
    $done = PatientCase::factory()->create(['branch_id' => $this->branch->id]);
    Sample::factory()->create(['case_id' => $done->id]);
    $deferredOnly = PatientCase::factory()->create(['branch_id' => $this->branch->id]);
    Sample::factory()->create(['case_id' => $deferredOnly->id]);
    CaseDonor::factory()->create(['case_id' => $deferredOnly->id, 'status' => CaseDonorStatus::Deferred]);
    $awaitingExam = PatientCase::factory()->create(['branch_id' => $this->branch->id]);
    Sample::factory()->create(['case_id' => $awaitingExam->id]);
    CaseDonor::factory()->create(['case_id' => $awaitingExam->id]);
    $untouched = PatientCase::factory()->create(['branch_id' => $this->branch->id]);
    PatientCase::factory()->create(['branch_id' => $this->branch->id, 'status' => CaseStatus::Cancelled]);

    $ids = signInToBranch($this, $this->technologist, $this->branch)
        ->getJson('/api/blood-bank/sample-collection/pending')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toEqualCanonicalizing([$this->case->id, $untouched->id]);
});

it('returns 403 FORBIDDEN when collecting for another branch\'s case', function () {
    $foreign = PatientCase::factory()->create();

    signInToBranch($this, $this->technologist, $this->branch)
        ->postJson("/api/blood-bank/cases/{$foreign->id}/samples", ['subjects' => [['subject_type' => 'patient']]])
        ->assertForbidden()
        ->assertJson(['code' => 'FORBIDDEN']);
});
