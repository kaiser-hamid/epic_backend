<?php

use Modules\BloodBank\Enums\BloodBankPermission;
use Modules\BloodBank\Enums\CaseDonorSource;
use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Enums\SampleSubject;
use Modules\BloodBank\Enums\UnitStatus;
use Modules\BloodBank\Models\CaseComponent;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\Collection;
use Modules\BloodBank\Models\ComponentType;
use Modules\BloodBank\Models\Donor;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Sample;
use Modules\BloodBank\Models\StorageLocation;
use Modules\BloodBank\Models\TestDefinition;
use Modules\BloodBank\Models\TestResult;
use Modules\BloodBank\Models\Unit;
use Modules\BloodBank\Models\UnitMovement;
use Modules\BloodBank\Services\DonorScreeningResults;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Patient\Enums\BloodGroup;
use Modules\Shared\Patient\Models\PatientMaster;
use Modules\Shared\Printing\Models\PrintLog;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->staff = User::factory()->create(['name' => 'Rina Akter']);
    grantInBranch(
        $this->staff,
        $this->branch,
        BloodBankPermission::CaseClose,
        BloodBankPermission::SampleCollect,
        BloodBankPermission::SerologyRecord,
        BloodBankPermission::CollectionCollect,
        BloodBankPermission::IssueRecord,
    );

    $this->pcv = ComponentType::factory()->create(['code' => 'PCV', 'unit_prefix' => 'BC']);
    $this->fridge = StorageLocation::factory()->create(['branch_id' => $this->branch->id]);

    $this->case = PatientCase::factory()->create([
        'branch_id' => $this->branch->id,
        'billing_id' => Billing::factory()->create(['invoice_no' => 'NB2611210001'])->id,
        'patient_master_id' => PatientMaster::factory()->create(['name' => 'Rahim Uddin'])->id,
    ]);
    CaseComponent::factory()->create(['case_id' => $this->case->id, 'component_type_id' => $this->pcv->id, 'quantity' => 1]);

    $this->originalDonor = CaseDonor::factory()->create([
        'case_id' => PatientCase::factory()->create(['branch_id' => $this->branch->id, 'status' => CaseStatus::Closed])->id,
        'donor_id' => Donor::factory()->create(['donor_no' => 'D2611000007', 'name' => 'Karim Mia']),
        'status' => CaseDonorStatus::Collected,
    ]);
    $this->unit = Unit::factory()->create([
        'branch_id' => $this->branch->id,
        'collection_id' => Collection::factory()->create(['case_donor_id' => $this->originalDonor->id])->id,
        'component_type_id' => $this->pcv->id,
        'status' => UnitStatus::Available,
        'storage_location_id' => $this->fridge->id,
        'blood_group' => BloodGroup::BPositive,
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

    $this->as = fn () => signInToBranch($this, $this->staff, $this->branch);
    $this->check = fn (?string $unitNo = null) => ($this->as)()
        ->getJson("/api/blood-bank/cases/{$this->case->id}/stock-units/check?unit_no=".($unitNo ?? $this->unit->unit_no));
    $this->take = fn (?string $unitNo = null, ?PatientCase $case = null) => ($this->as)()
        ->postJson('/api/blood-bank/cases/'.($case ?? $this->case)->id.'/samples', [
            'subjects' => [['subject_type' => 'donor', 'unit_no' => $unitNo ?? $this->unit->unit_no]],
        ]);
    $this->stockRow = fn (): CaseDonor => CaseDonor::where('case_id', $this->case->id)->where('source', CaseDonorSource::Stock)->sole();
    $this->recordSerology = fn (array $overrides = []) => ($this->as)()
        ->putJson("/api/blood-bank/cases/{$this->case->id}/donors/".($this->stockRow)()->id.'/serology', [...$this->serology, ...$overrides]);
    $this->reserve = fn (CaseDonor $caseDonor) => ($this->as)()
        ->postJson("/api/blood-bank/cases/{$this->case->id}/donors/{$caseDonor->id}/stock-unit");
});

it('takes a stock bag for the case: holds the unit, adds a fit stock row and draws its sample with a donor label', function () {
    ($this->take)()
        ->assertCreated()
        ->assertJsonPath('data.subjects.1.source', 'stock')
        ->assertJsonPath('data.subjects.1.case_donor_status', 'fit')
        ->assertJsonPath('data.subjects.1.number', 'D2611000007')
        ->assertJsonPath('data.subjects.1.stock_unit.unit_no', $this->unit->unit_no)
        ->assertJsonPath('data.subjects.1.stock_unit.status', 'held')
        ->assertJsonPath('data.subjects.1.is_collectable', false);

    $row = ($this->stockRow)();
    $sample = Sample::where('case_donor_id', $row->id)->sole();

    expect($row->unit_id)->toBe($this->unit->id)
        ->and($row->donor_id)->toBe($this->originalDonor->donor_id)
        ->and($row->billing_id)->toBe($this->case->billing_id)
        ->and($row->status)->toBe(CaseDonorStatus::Fit)
        ->and($sample->subject_type)->toBe(SampleSubject::Donor)
        ->and(PrintLog::where('document_type', 'bb.sample_label')->where('printable_id', $sample->id)->count())->toBe(1)
        ->and($this->unit->refresh()->status)->toBe(UnitStatus::Held)
        ->and($this->unit->case_id)->toBe($this->case->id)
        ->and($this->unit->storage_location_id)->toBe($this->fridge->id);

    $movement = UnitMovement::where('unit_id', $this->unit->id)->sole();
    expect($movement->from_status)->toBe(UnitStatus::Available)
        ->and($movement->to_status)->toBe(UnitStatus::Held)
        ->and($movement->case_id)->toBe($this->case->id);
});

it('checks a scanned stock bag without holding it or adding it to the case', function () {
    ($this->check)()
        ->assertOk()
        ->assertJsonPath('data.unit_no', $this->unit->unit_no)
        ->assertJsonPath('data.status', 'available')
        ->assertJsonPath('data.storage_location.id', $this->fridge->id);

    expect($this->unit->refresh()->status)->toBe(UnitStatus::Available)
        ->and(CaseDonor::where('source', CaseDonorSource::Stock)->exists())->toBeFalse();
});

it('takes a donor row with a case donor id or a unit no, never both, and neither on the patient row', function (array $subject) {
    ($this->as)()
        ->postJson("/api/blood-bank/cases/{$this->case->id}/samples", ['subjects' => [$subject]])
        ->assertUnprocessable()
        ->assertJson(['code' => 'VALIDATION_FAILED']);
})->with([
    'donor with neither' => [['subject_type' => 'donor']],
    'donor with both' => [['subject_type' => 'donor', 'case_donor_id' => 1, 'unit_no' => 'BC1']],
    'patient with a unit no' => [['subject_type' => 'patient', 'unit_no' => 'BC1']],
]);

it('refuses at Collect a checked bag that another case took meanwhile', function () {
    ($this->check)()->assertOk();

    $other = PatientCase::factory()->create(['branch_id' => $this->branch->id]);
    $this->unit->update(['status' => UnitStatus::Held, 'case_id' => $other->id]);

    ($this->take)()->assertConflict()->assertJson(['code' => 'UNIT_NOT_AVAILABLE']);

    expect(CaseDonor::where('source', CaseDonorSource::Stock)->exists())->toBeFalse()
        ->and(Sample::where('case_id', $this->case->id)->exists())->toBeFalse();
});

it('refuses to list a unit that is not free, not needed, expired or unknown', function (Closure $arrange, string $code, int $status) {
    $unitNo = $arrange->call($this);

    ($this->check)($unitNo)->assertStatus($status)->assertJson(['code' => $code]);
})->with([
    'held for another case' => [function (): ?string {
        $other = PatientCase::factory()->create(['branch_id' => $this->branch->id]);
        $this->unit->update(['status' => UnitStatus::Held, 'case_id' => $other->id]);

        return null;
    }, 'UNIT_NOT_AVAILABLE', 409],
    'a component the case needs no more of' => [function (): ?string {
        CaseComponent::query()->update(['component_type_id' => ComponentType::factory()->create(['code' => 'FFP'])->id]);

        return null;
    }, 'UNIT_NOT_NEEDED', 422],
    'expired' => [function (): ?string {
        $this->unit->update(['expires_at' => now()->subHour()]);

        return null;
    }, 'UNIT_EXPIRED', 422],
    'unknown unit no' => [fn (): string => 'BC0000000000', 'UNIT_NOT_FOUND', 404],
]);

it('sends a deferred stock bag back to stock, and takes it again when the deferral is switched to Accept', function () {
    ($this->take)()->assertCreated();

    ($this->recordSerology)(['decision' => 'deferred', 'deferral_reason' => 'Group mismatch'])->assertOk();

    expect($this->unit->refresh()->status)->toBe(UnitStatus::Available)
        ->and($this->unit->case_id)->toBeNull()
        ->and(UnitMovement::where('unit_id', $this->unit->id)->latest('id')->first()->case_id)->toBe($this->case->id);

    ($this->recordSerology)()->assertOk();

    expect($this->unit->refresh()->status)->toBe(UnitStatus::Held)
        ->and($this->unit->case_id)->toBe($this->case->id)
        ->and(($this->stockRow)()->status)->toBe(CaseDonorStatus::Eligible);
});

it('quarantines a stock bag deferred with a reactive result, so it cannot be taken again', function () {
    ($this->take)()->assertCreated();

    ($this->recordSerology)(['hbsag' => 'reactive', 'decision' => 'deferred', 'deferral_reason' => 'HBsAg reactive'])->assertOk();

    expect($this->unit->refresh()->status)->toBe(UnitStatus::Quarantined)
        ->and($this->unit->case_id)->toBeNull();

    ($this->recordSerology)(['amendment_reason' => 'Re-tested'])
        ->assertConflict()
        ->assertJson(['code' => 'UNIT_NOT_AVAILABLE']);
});

it('prints the patient label of an accepted stock bag as its first patient label print and reserves the unit', function () {
    PrintLog::factory()->create([
        'document_type' => 'bb.storage_label',
        'printable_type' => 'bb.unit',
        'printable_id' => $this->unit->id,
    ]);
    ($this->take)()->assertCreated();
    ($this->recordSerology)()->assertOk();

    $response = ($this->reserve)(($this->stockRow)())
        ->assertCreated()
        ->assertJsonPath('data.collected_units.0.unit_no', $this->unit->unit_no)
        ->assertJsonPath('data.collected_units.0.status', 'reserved')
        ->assertJsonPath('data.collected_units.0.label_print.is_reprint', false);

    expect($response->json('data.collected_units.0.label_command'))->toContain('Rahim Uddin')
        ->and($this->unit->refresh()->status)->toBe(UnitStatus::Reserved)
        ->and($this->unit->storage_location_id)->toBe($this->fridge->id)
        ->and(($this->stockRow)()->status)->toBe(CaseDonorStatus::Collected)
        ->and(PrintLog::where('document_type', 'bb.patient_label')->sole()->printable_id)->toBe($this->unit->id);
});

it('keeps the bag and stock steps apart: no bag for a stock row, no patient label for a real donor', function () {
    ($this->take)()->assertCreated();
    ($this->recordSerology)()->assertOk();
    $donor = CaseDonor::factory()->create(['case_id' => $this->case->id, 'sequence_no' => 9, 'status' => CaseDonorStatus::Eligible]);

    ($this->reserve)($donor)->assertUnprocessable()->assertJson(['code' => 'CASE_DONOR_NOT_STOCK_UNIT']);

    ($this->as)()
        ->postJson("/api/blood-bank/cases/{$this->case->id}/donors/".($this->stockRow)()->id.'/collection', ['units' => []])
        ->assertUnprocessable()
        ->assertJson(['code' => 'CASE_DONOR_IS_STOCK_UNIT']);
});

it('issues a stock unit through Blood Issue and closes the case', function () {
    Sample::factory()->create(['case_id' => $this->case->id, 'subject_type' => SampleSubject::Patient]);
    ($this->take)()->assertCreated();
    ($this->recordSerology)()->assertOk();
    ($this->reserve)(($this->stockRow)())->assertCreated();

    ($this->as)()->postJson("/api/blood-bank/cases/{$this->case->id}/blood-issue", [
        'patient_blood_group' => 'B_POS',
        'technologist_id' => $this->staff->id,
        'physician_id' => User::factory()->create()->id,
        'transfusion_reason' => 'Anaemia',
        'units' => [['unit_id' => $this->unit->id]],
    ])->assertCreated()->assertHeader('Content-Type', 'application/pdf');

    expect($this->unit->refresh()->status)->toBe(UnitStatus::Issued)
        ->and($this->case->refresh()->status)->toBe(CaseStatus::Closed);
});

it('prints the re-test of a stock unit on the report, falling back to the original donor per test', function () {
    $definition = fn (string $code): int => TestDefinition::where('code', $code)->value('id');
    $originalSample = Sample::factory()->create(['case_id' => $this->originalDonor->case_id, 'case_donor_id' => $this->originalDonor->id, 'subject_type' => SampleSubject::Donor]);
    TestResult::factory()->create(['sample_id' => $originalSample->id, 'test_definition_id' => $definition('HBSAG'), 'result_value' => 'non_reactive']);
    TestResult::factory()->create(['sample_id' => $originalSample->id, 'test_definition_id' => $definition('MALARIAL_PARASITE'), 'result_value' => 'not_found']);

    $stockRow = CaseDonor::factory()->create([
        'case_id' => $this->case->id,
        'donor_id' => $this->originalDonor->donor_id,
        'source' => CaseDonorSource::Stock,
        'unit_id' => $this->unit->id,
        'status' => CaseDonorStatus::Eligible,
    ]);
    $retestSample = Sample::factory()->create(['case_id' => $this->case->id, 'case_donor_id' => $stockRow->id, 'subject_type' => SampleSubject::Donor]);
    TestResult::factory()->create(['sample_id' => $retestSample->id, 'test_definition_id' => $definition('HBSAG'), 'result_value' => 'reactive']);

    $results = collect(app(DonorScreeningResults::class)->forRetestedUnit($this->originalDonor, $stockRow))->pluck('result', 'code');

    expect($results['HBSAG'])->toBe('reactive')
        ->and($results['MALARIAL_PARASITE'])->toBe('not_found');
});

it('sends units still held for the case back to stock when the case is closed', function () {
    ($this->take)()->assertCreated();

    ($this->as)()->postJson("/api/blood-bank/cases/{$this->case->id}/close", ['reason' => 'Patient did not return'])->assertOk();

    expect($this->unit->refresh()->status)->toBe(UnitStatus::Available)
        ->and($this->unit->case_id)->toBeNull()
        ->and($this->case->refresh()->status)->toBe(CaseStatus::Closed);
});
