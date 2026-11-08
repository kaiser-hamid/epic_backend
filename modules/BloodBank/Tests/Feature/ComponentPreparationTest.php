<?php

use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Enums\BloodBankPermission;
use Modules\BloodBank\Enums\CaseDonorStatus;
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
use Modules\Shared\Audit\Models\AuditLog;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Patient\Enums\BloodGroup;
use Modules\Shared\Patient\Models\PatientMaster;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->technologist = User::factory()->create();
    grantInBranch(
        $this->technologist,
        $this->branch,
        BloodBankPermission::UnitView,
        BloodBankPermission::UnitPrepare,
        BloodBankPermission::UnitLabelPrint,
    );

    $this->wholeBlood = ComponentType::factory()->create(['code' => 'WB', 'shelf_life_hours' => 35 * 24]);
    $this->pcv = ComponentType::factory()->create(['code' => 'PCV', 'shelf_life_hours' => 42 * 24]);
    $this->plasma = ComponentType::factory()->create(['code' => 'FP', 'shelf_life_hours' => 365 * 24]);
    $this->fridge = StorageLocation::factory()->create(['branch_id' => $this->branch->id]);
    $this->freezer = StorageLocation::factory()->create(['branch_id' => $this->branch->id]);

    $this->case = PatientCase::factory()->create([
        'branch_id' => $this->branch->id,
        'patient_master_id' => PatientMaster::factory()->create(['name' => 'Rahim Uddin', 'patient_no' => 'P2611020001'])->id,
    ]);
    $this->billed = CaseComponent::factory()->create(['case_id' => $this->case->id, 'component_type_id' => $this->pcv->id, 'quantity' => 1]);

    $this->caseDonor = CaseDonor::factory()->create([
        'case_id' => $this->case->id,
        'donor_id' => Donor::factory()->create(['donor_no' => 'D2611000001', 'blood_group' => BloodGroup::BPositive]),
        'status' => CaseDonorStatus::Collected,
    ]);
    $this->bag = Collection::factory()->create([
        'case_donor_id' => $this->caseDonor->id,
        'bag_no' => 'B2611020001',
        'collected_at' => now()->subHours(2)->startOfMinute(),
    ]);

    $this->forPatient = fn (ComponentType $type): array => ['component_type_id' => $type->id, 'for_case' => true];
    $this->toStock = fn (ComponentType $type, ?StorageLocation $location = null): array => ['component_type_id' => $type->id, 'storage_location_id' => ($location ?? $this->freezer)->id];

    $this->prepare = fn (array $units, ?Collection $bag = null, ?PatientCase $case = null, array $extra = []) => signInToBranch($this, $this->technologist, $this->branch)
        ->postJson(sprintf('/api/blood-bank/cases/%d/bags/%d/units', ($case ?? $this->case)->id, ($bag ?? $this->bag)->id), ['units' => $units, ...$extra]);
});

it('separates a bag into the billed component for the patient and the rest for stock', function () {
    ($this->prepare)([
        [...($this->forPatient)($this->pcv), 'volume_ml' => 250],
        [...($this->toStock)($this->plasma), 'volume_ml' => 200],
    ])
        ->assertCreated()
        ->assertJsonCount(2, 'data.prepared_unit_ids')
        ->assertJsonPath('data.can_prepare', false)
        ->assertJsonPath('data.units.0.unit_no', 'B2611020001-PCV')
        ->assertJsonPath('data.units.0.status', 'reserved')
        ->assertJsonPath('data.units.0.storage_location', null)
        ->assertJsonPath('data.units.0.reserved_for.patient_name', 'Rahim Uddin')
        ->assertJsonPath('data.units.1.unit_no', 'B2611020001-FP')
        ->assertJsonPath('data.units.1.status', 'available')
        ->assertJsonPath('data.units.1.reserved_for', null)
        ->assertJsonPath('data.case.components.0.held_count', 1)
        ->assertJsonPath('data.case.components.0.needed_count', 0);

    $pcv = Unit::where('unit_no', 'B2611020001-PCV')->sole();
    $plasma = Unit::where('unit_no', 'B2611020001-FP')->sole();

    expect($pcv->case_id)->toBe($this->case->id)
        ->and($pcv->branch_id)->toBe($this->branch->id)
        ->and($pcv->blood_group)->toBe(BloodGroup::BPositive)
        ->and($pcv->parent_unit_id)->toBeNull()
        ->and($pcv->expires_at->equalTo($this->bag->collected_at->copy()->addDays(42)))->toBeTrue()
        ->and($plasma->case_id)->toBeNull()
        ->and($plasma->storage_location_id)->toBe($this->freezer->id)
        ->and(UnitMovement::where('unit_id', $pcv->id)->sole()->to_status)->toBe(UnitStatus::Reserved)
        ->and(UnitMovement::where('unit_id', $plasma->id)->sole()->to_location_id)->toBe($this->freezer->id)
        ->and(AuditLog::where('action', BloodBankAuditAction::UnitsPrepared)->count())->toBe(1);
});

it('keeps the bag as whole blood for a patient billed for whole blood', function () {
    $this->billed->update(['component_type_id' => $this->wholeBlood->id]);

    ($this->prepare)([($this->forPatient)($this->wholeBlood)])
        ->assertCreated()
        ->assertJsonPath('data.units.0.unit_no', 'B2611020001')
        ->assertJsonPath('data.units.0.status', 'reserved');
});

it('requires the preparation to give the patient what the case was billed for', function (Closure $setup, Closure $units) {
    $setup->call($this);

    ($this->prepare)($units->call($this))
        ->assertUnprocessable()
        ->assertJson(['code' => 'COMPONENT_REQUIREMENT_NOT_MET']);

    expect(Unit::count())->toBe(0);
})->with([
    'billed PCV, nothing for the patient' => [fn () => null, fn () => [($this->toStock)($this->pcv, $this->fridge), ($this->toStock)($this->plasma)]],
    'billed PCV, kept as whole blood' => [fn () => null, fn () => [($this->toStock)($this->wholeBlood, $this->fridge)]],
    'billed whole blood, separated' => [fn () => $this->billed->update(['component_type_id' => $this->wholeBlood->id]), fn () => [($this->toStock)($this->plasma)]],
    'billed whole blood, sent to stock' => [fn () => $this->billed->update(['component_type_id' => $this->wholeBlood->id]), fn () => [($this->toStock)($this->wholeBlood, $this->fridge)]],
]);

it('reserves for the patient only a billed component, up to the billed quantity', function () {
    ($this->prepare)([($this->forPatient)($this->pcv), ($this->forPatient)($this->plasma)])
        ->assertUnprocessable()
        ->assertJson(['code' => 'COMPONENT_NOT_NEEDED']);

    ($this->prepare)([($this->forPatient)($this->pcv), ($this->forPatient)($this->pcv)])
        ->assertUnprocessable()
        ->assertJson(['code' => 'COMPONENT_NOT_NEEDED']);

    expect(Unit::count())->toBe(0);
});

it('sends everything to stock once the case holds what it was billed for', function () {
    Unit::factory()->create(['branch_id' => $this->branch->id, 'case_id' => $this->case->id, 'component_type_id' => $this->pcv->id, 'status' => UnitStatus::Reserved]);

    ($this->prepare)([($this->forPatient)($this->pcv)])
        ->assertUnprocessable()
        ->assertJson(['code' => 'COMPONENT_NOT_NEEDED']);

    ($this->prepare)([($this->toStock)($this->pcv, $this->fridge), ($this->toStock)($this->plasma), ($this->toStock)($this->plasma)])
        ->assertCreated();

    expect(Unit::where('collection_id', $this->bag->id)->orderBy('id')->pluck('unit_no')->all())
        ->toBe(['B2611020001-PCV', 'B2611020001-FP-1', 'B2611020001-FP-2']);
});

it('prepares a bag only once', function () {
    $units = [($this->forPatient)($this->pcv)];
    ($this->prepare)($units)->assertCreated();

    ($this->prepare)($units)->assertStatus(409)->assertJson(['code' => 'BAG_ALREADY_PREPARED']);

    expect(Unit::count())->toBe(1);
});

it('rejects an invalid preparation form', function (Closure $units, string $field) {
    ($this->prepare)($units->call($this))
        ->assertUnprocessable()
        ->assertJson(['code' => 'VALIDATION_FAILED'])
        ->assertJsonValidationErrors($field);
})->with([
    'whole blood and a component' => [fn () => [($this->toStock)($this->wholeBlood, $this->fridge), ($this->forPatient)($this->pcv)], 'units'],
    'stock without a location' => [fn () => [($this->forPatient)($this->pcv), ['component_type_id' => $this->plasma->id]], 'units.1.storage_location_id'],
    'another branch\'s fridge' => [fn () => [($this->forPatient)($this->pcv), ($this->toStock)($this->plasma, StorageLocation::factory()->create())], 'units.1.storage_location_id'],
]);

it('accepts a preparation time sent by the browser in UTC', function () {
    $preparedAt = now()->subHour()->startOfMinute();

    ($this->prepare)([($this->forPatient)($this->pcv)], extra: ['prepared_at' => $preparedAt->copy()->utc()->format('Y-m-d\TH:i:s.v\Z')])
        ->assertCreated()
        ->assertJsonPath('data.units.0.prepared_at', $preparedAt->toIso8601String());

    expect(Unit::sole()->prepared_at->equalTo($preparedAt))->toBeTrue();
});

it('refuses another branch\'s case', function () {
    $otherBag = Collection::factory()->create();

    ($this->prepare)([($this->toStock)($this->plasma)], $otherBag, $otherBag->caseDonor->patientCase)
        ->assertForbidden()
        ->assertJson(['code' => 'FORBIDDEN']);
});

it('opens the bag for a scanned bag number of this branch only', function () {
    $lookup = fn () => signInToBranch($this, $this->technologist, $this->branch)
        ->getJson('/api/blood-bank/component-preparation?bag_no=B2611020001');

    $lookup()
        ->assertOk()
        ->assertJsonPath('data.bag.bag_no', 'B2611020001')
        ->assertJsonPath('data.bag.patient.name', 'Rahim Uddin')
        ->assertJsonPath('data.case.id', $this->case->id)
        ->assertJsonPath('data.case.components.0.code', 'PCV')
        ->assertJsonPath('data.case.components.0.needed_count', 1)
        ->assertJsonPath('data.units', [])
        ->assertJsonPath('data.can_prepare', true);

    $this->case->update(['branch_id' => Branch::factory()->create()->id]);

    $lookup()->assertNotFound()->assertJson(['code' => 'BAG_NOT_FOUND']);
});

it('prints the component label with the donor screening and requires a reason for a reprint', function () {
    $sample = Sample::factory()->create(['case_id' => $this->case->id, 'case_donor_id' => $this->caseDonor->id, 'subject_type' => SampleSubject::Donor]);
    TestResult::factory()->create([
        'sample_id' => $sample->id,
        'test_definition_id' => TestDefinition::where('code', 'HBSAG')->value('id'),
        'result_value' => 'non_reactive',
    ]);
    TestResult::factory()->create([
        'sample_id' => $sample->id,
        'test_definition_id' => TestDefinition::where('code', 'MALARIAL_PARASITE')->value('id'),
        'result_value' => 'not_found',
    ]);
    ($this->prepare)([($this->forPatient)($this->pcv)])->assertCreated();
    $unit = Unit::sole();

    $print = fn (array $payload = []) => signInToBranch($this, $this->technologist, $this->branch)
        ->postJson("/api/blood-bank/cases/{$this->case->id}/unit-labels", ['unit_ids' => [$unit->id], ...$payload]);

    $print()
        ->assertOk()
        ->assertJsonPath('data.0.unit_no', 'B2611020001-PCV')
        ->assertJsonPath('data.0.blood_group', 'B_POS')
        ->assertJsonPath('data.0.donor_no', 'D2611000001')
        ->assertJsonPath('data.0.expires_at', $unit->expires_at->toIso8601String())
        ->assertJsonPath('data.0.screening.0.code', 'HBSAG')
        ->assertJsonPath('data.0.screening.0.result', 'non_reactive')
        ->assertJsonPath('data.0.screening.1.code', 'MALARIAL_PARASITE')
        ->assertJsonPath('data.0.screening.1.method', 'ICT method')
        ->assertJsonPath('data.0.screening.1.result', 'not_found')
        ->assertJsonPath('data.0.reserved_for.patient_no', 'P2611020001')
        ->assertJsonPath('data.0.label_print.is_reprint', false);

    $print()->assertUnprocessable()->assertJson(['code' => 'REPRINT_REASON_REQUIRED']);

    $print(['reprint_reason' => 'Label wet'])->assertOk()->assertJsonPath('data.0.label_print.is_reprint', true);
});

it('does not print a label for a unit of another case', function () {
    signInToBranch($this, $this->technologist, $this->branch)
        ->postJson("/api/blood-bank/cases/{$this->case->id}/unit-labels", ['unit_ids' => [Unit::factory()->create()->id]])
        ->assertUnprocessable()
        ->assertJson(['code' => 'UNIT_NOT_IN_CASE']);
});

it('lists unprepared bags as pending and this branch\'s stock on the units tab', function () {
    $preparedBag = Collection::factory()->create([
        'case_donor_id' => CaseDonor::factory()->create(['case_id' => $this->case->id, 'sequence_no' => 2]),
        'bag_no' => 'B2611020002',
    ]);
    ($this->prepare)([($this->forPatient)($this->pcv), ($this->toStock)($this->plasma)], $preparedBag)->assertCreated();
    Unit::factory()->create(['status' => UnitStatus::Available]);

    signInToBranch($this, $this->technologist, $this->branch)
        ->getJson('/api/blood-bank/component-preparation/pending')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.bag_no', 'B2611020001');

    signInToBranch($this, $this->technologist, $this->branch)
        ->getJson('/api/blood-bank/units?filter[status]=available')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.unit_no', 'B2611020002-FP')
        ->assertJsonPath('data.0.storage_location.id', $this->freezer->id);
});
