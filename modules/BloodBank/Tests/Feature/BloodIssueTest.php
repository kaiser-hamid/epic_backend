<?php

use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Enums\BloodBankPermission;
use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Enums\PrintDocumentType;
use Modules\BloodBank\Enums\SampleStatus;
use Modules\BloodBank\Enums\SampleSubject;
use Modules\BloodBank\Enums\TestResultStatus;
use Modules\BloodBank\Enums\UnitStatus;
use Modules\BloodBank\Models\CaseComponent;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\Collection;
use Modules\BloodBank\Models\ComponentType;
use Modules\BloodBank\Models\Crossmatch;
use Modules\BloodBank\Models\Donor;
use Modules\BloodBank\Models\DonorScreening;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Sample;
use Modules\BloodBank\Models\StorageLocation;
use Modules\BloodBank\Models\TestDefinition;
use Modules\BloodBank\Models\TestResult;
use Modules\BloodBank\Models\Unit;
use Modules\BloodBank\Models\UnitMovement;
use Modules\Shared\Audit\Models\AuditLog;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Patient\Enums\BloodGroup;
use Modules\Shared\Patient\Models\PatientMaster;
use Modules\Shared\Printing\Models\PrintLog;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->clerk = User::factory()->create();
    grantInBranch(
        $this->clerk,
        $this->branch,
        BloodBankPermission::IssueView,
        BloodBankPermission::IssueRecord,
        BloodBankPermission::IssueReportPrint,
    );
    $this->technologist = User::factory()->create(['name' => 'Rina Akter']);
    $this->physician = User::factory()->create(['name' => 'Dr. Kamal']);

    $this->pcv = ComponentType::factory()->create(['code' => 'PCV', 'name' => 'Packed Cell Volume']);
    $this->fridge = StorageLocation::factory()->create(['branch_id' => $this->branch->id]);

    $this->case = PatientCase::factory()->create([
        'branch_id' => $this->branch->id,
        'billing_id' => Billing::factory()->create(['invoice_no' => 'NB2611030001'])->id,
        'patient_master_id' => PatientMaster::factory()->create(['name' => 'Rahim Uddin'])->id,
    ]);
    $this->billed = CaseComponent::factory()->create(['case_id' => $this->case->id, 'component_type_id' => $this->pcv->id, 'quantity' => 1]);
    $this->patientSample = Sample::factory()->create(['case_id' => $this->case->id, 'subject_type' => SampleSubject::Patient]);

    $this->caseDonor = CaseDonor::factory()->create([
        'case_id' => $this->case->id,
        'donor_id' => Donor::factory()->create(['donor_no' => 'D2611000001', 'blood_group' => BloodGroup::BPositive]),
        'status' => CaseDonorStatus::Collected,
    ]);
    $this->screening = DonorScreening::factory()->create(['case_donor_id' => $this->caseDonor->id]);
    $donorSample = Sample::factory()->create(['case_id' => $this->case->id, 'case_donor_id' => $this->caseDonor->id, 'subject_type' => SampleSubject::Donor]);
    TestResult::factory()->create([
        'sample_id' => $donorSample->id,
        'test_definition_id' => TestDefinition::where('code', 'HBSAG')->value('id'),
        'result_value' => 'non_reactive',
    ]);
    $this->bag = Collection::factory()->create(['case_donor_id' => $this->caseDonor->id, 'bag_no' => 'B2611030001']);
    $this->unit = Unit::factory()->create([
        'branch_id' => $this->branch->id,
        'collection_id' => $this->bag->id,
        'component_type_id' => $this->pcv->id,
        'case_id' => $this->case->id,
        'status' => UnitStatus::Reserved,
        'storage_location_id' => $this->fridge->id,
        'blood_group' => BloodGroup::BPositive,
    ]);

    $this->vitals = ['case_donor_id' => $this->caseDonor->id, 'pulse_bpm' => 80, 'bp_systolic' => 120, 'bp_diastolic' => 80, 'temperature_f' => 98.4];
    $this->payload = fn (array $overrides = []): array => [
        'patient_blood_group' => 'B_POS',
        'technologist_id' => $this->technologist->id,
        'physician_id' => $this->physician->id,
        'units' => [['unit_id' => $this->unit->id, 'major_result' => 'compatible', 'minor_result' => 'compatible']],
        'post_donation' => [$this->vitals],
        ...$overrides,
    ];
    $this->issue = fn (array $payload) => signInToBranch($this, $this->clerk, $this->branch)
        ->postJson("/api/blood-bank/cases/{$this->case->id}/blood-issue", $payload);
});

it('issues the units, records the crossmatch and vitals, closes the case and returns the report PDF', function () {
    $response = ($this->issue)(($this->payload)());

    $response->assertCreated()->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF');

    $this->unit->refresh();
    expect($this->unit->status)->toBe(UnitStatus::Issued)
        ->and($this->unit->case_id)->toBe($this->case->id)
        ->and($this->unit->storage_location_id)->toBeNull();

    $movement = UnitMovement::where('unit_id', $this->unit->id)->sole();
    expect($movement->from_status)->toBe(UnitStatus::Reserved)
        ->and($movement->to_status)->toBe(UnitStatus::Issued)
        ->and($movement->from_location_id)->toBe($this->fridge->id);

    $crossmatch = Crossmatch::sole();
    expect($crossmatch->patient_sample_id)->toBe($this->patientSample->id)
        ->and($crossmatch->isCompatible())->toBeTrue()
        ->and($crossmatch->tested_by)->toBe($this->technologist->id)
        ->and($crossmatch->reviewed_by)->toBe($this->physician->id)
        ->and($crossmatch->status)->toBe(TestResultStatus::Reviewed);

    expect(TestResult::where('sample_id', $this->patientSample->id)->sole()->result_value)->toBe('B_POS')
        ->and($this->patientSample->refresh()->status)->toBe(SampleStatus::Tested);

    $this->screening->refresh();
    expect($this->screening->post_pulse_bpm)->toBe(80)
        ->and($this->screening->post_bp_systolic)->toBe(120)
        ->and($this->screening->post_recorded_by)->toBe($this->clerk->id);

    $this->case->refresh();
    expect($this->case->status)->toBe(CaseStatus::Closed)
        ->and($this->case->supplied_at)->not->toBeNull();

    $print = PrintLog::sole();
    expect($print->document_type)->toBe(PrintDocumentType::CompatibilityReport->value)
        ->and($print->printable_id)->toBe($crossmatch->id)
        ->and($print->is_reprint)->toBeFalse();

    expect(AuditLog::where('action', BloodBankAuditAction::BloodIssued)->count())->toBe(1)
        ->and(AuditLog::where('action', BloodBankAuditAction::CaseClosed)->count())->toBe(1);
});

it('keeps the case open while billed components remain to be issued', function () {
    $this->billed->update(['quantity' => 2]);

    ($this->issue)(($this->payload)())->assertCreated();

    $this->case->refresh();
    expect($this->case->status)->toBe(CaseStatus::Open)
        ->and($this->case->supplied_at)->not->toBeNull();
});

it('issues nothing when a rule is broken', function (Closure $setup, Closure $payload, int $status, string $code) {
    $setup->call($this);
    $unitStatus = $this->unit->refresh()->status;

    ($this->issue)($payload->call($this))
        ->assertStatus($status)
        ->assertJson(['code' => $code]);

    expect($this->unit->refresh()->status)->toBe($unitStatus)
        ->and(Crossmatch::count())->toBe(0)
        ->and(PrintLog::count())->toBe(0)
        ->and($this->screening->refresh()->post_recorded_at)->toBeNull()
        ->and($this->case->refresh()->status)->toBe(CaseStatus::Open);
})->with([
    'incompatible crossmatch' => [
        fn () => null,
        fn () => ($this->payload)(['units' => [['unit_id' => $this->unit->id, 'major_result' => 'compatible', 'minor_result' => 'incompatible']]]),
        422, 'CROSSMATCH_INCOMPATIBLE',
    ],
    'post-donation vitals missing' => [fn () => null, fn () => ($this->payload)(['post_donation' => []]), 422, 'POST_DONATION_VITALS_REQUIRED'],
    'unit not reserved' => [fn () => $this->unit->update(['status' => UnitStatus::Available]), fn () => ($this->payload)(), 409, 'UNIT_NOT_RESERVED'],
    'unit of another case' => [fn () => $this->unit->update(['case_id' => PatientCase::factory()->create()->id]), fn () => ($this->payload)(), 422, 'UNIT_NOT_IN_CASE'],
    'unit expired' => [fn () => $this->unit->update(['expires_at' => now()->subMinute()]), fn () => ($this->payload)(), 422, 'UNIT_EXPIRED'],
    'patient sample not collected' => [fn () => $this->patientSample->update(['status' => SampleStatus::Rejected]), fn () => ($this->payload)(), 422, 'PATIENT_SAMPLE_NOT_COLLECTED'],
    'blood group changed without a reason' => [
        fn () => TestResult::factory()->create([
            'sample_id' => $this->patientSample->id,
            'test_definition_id' => TestDefinition::where('code', 'BLOOD_GROUP')->value('id'),
            'result_value' => 'O_POS',
        ]),
        fn () => ($this->payload)(),
        422, 'AMENDMENT_REASON_REQUIRED',
    ],
]);

it('does not overwrite post-donation vitals already recorded', function () {
    $this->screening->update(['post_pulse_bpm' => 70, 'post_bp_systolic' => 110, 'post_bp_diastolic' => 70, 'post_temperature_f' => 98.0, 'post_recorded_by' => $this->clerk->id, 'post_recorded_at' => now()]);

    ($this->issue)(($this->payload)())->assertConflict()->assertJson(['code' => 'POST_DONATION_VITALS_RECORDED']);
    ($this->issue)(($this->payload)(['post_donation' => []]))->assertCreated();

    expect($this->screening->refresh()->post_pulse_bpm)->toBe(70);
});

it('requires the patient blood group until one is recorded', function () {
    ($this->issue)(($this->payload)(['patient_blood_group' => null]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['patient_blood_group' => 'Enter the patient blood group.']);
});

it('reprints the report only with a reason', function () {
    ($this->issue)(($this->payload)())->assertCreated();
    $reprint = fn (array $payload = []) => signInToBranch($this, $this->clerk, $this->branch)
        ->postJson("/api/blood-bank/cases/{$this->case->id}/compatibility-reports", ['unit_ids' => [$this->unit->id], ...$payload]);

    $reprint()->assertUnprocessable()->assertJson(['code' => 'REPRINT_REASON_REQUIRED']);
    $response = $reprint(['reprint_reason' => 'Paper jam']);

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF')
        ->and(PrintLog::where('is_reprint', true)->sole()->reprint_reason)->toBe('Paper jam');
});

it('shows the whole case by invoice no or donor no', function (string $query) {
    signInToBranch($this, $this->clerk, $this->branch)
        ->getJson("/api/blood-bank/blood-issue?{$query}")
        ->assertOk()
        ->assertJsonPath('data.case.id', $this->case->id)
        ->assertJsonPath('data.case.components.0.reserved_count', 1)
        ->assertJsonPath('data.case.components.0.remaining_count', 1)
        ->assertJsonPath('data.patient.name', 'Rahim Uddin')
        ->assertJsonPath('data.patient_sample.blood_group', null)
        ->assertJsonPath('data.donors.0.number', 'D2611000001')
        ->assertJsonPath('data.donors.0.bag.bag_no', 'B2611030001')
        ->assertJsonPath('data.donors.0.serology.hbsag', 'non_reactive')
        ->assertJsonPath('data.donors.0.examination.post', null)
        ->assertJsonPath('data.donors.0.needs_post_vitals', true)
        ->assertJsonPath('data.units.0.unit_no', $this->unit->unit_no)
        ->assertJsonPath('data.units.0.crossmatch', null)
        ->assertJsonPath('data.units.0.can_issue', true)
        ->assertJsonPath('data.can_issue', true);
})->with(['invoice_no=NB2611030001', 'donor_no=D2611000001']);

it('refuses a case of another branch', function () {
    $this->case->update(['branch_id' => Branch::factory()->create()->id]);

    ($this->issue)(($this->payload)())->assertForbidden()->assertJson(['code' => 'FORBIDDEN']);
    expect($this->unit->refresh()->status)->toBe(UnitStatus::Reserved);
});
