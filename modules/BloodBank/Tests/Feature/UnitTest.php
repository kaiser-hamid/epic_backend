<?php

use Modules\BloodBank\Enums\BloodBankPermission;
use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Enums\SampleSubject;
use Modules\BloodBank\Enums\UnitStatus;
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
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Patient\Enums\BloodGroup;
use Modules\Shared\Patient\Enums\Gender;
use Modules\Shared\Patient\Models\PatientMaster;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->technologist = User::factory()->create();
    grantInBranch($this->technologist, $this->branch, BloodBankPermission::UnitView, BloodBankPermission::UnitLabelPrint);

    $this->pcv = ComponentType::factory()->create(['code' => 'PCV', 'unit_prefix' => 'BC']);
    $this->ffp = ComponentType::factory()->create(['code' => 'FFP', 'unit_prefix' => 'BF']);
    $this->freezer = StorageLocation::factory()->create(['branch_id' => $this->branch->id]);

    $this->case = PatientCase::factory()->create([
        'branch_id' => $this->branch->id,
        'patient_master_id' => PatientMaster::factory()->create(['name' => 'Rahim Uddin', 'patient_no' => 'P2611020001'])->id,
    ]);
    $this->caseDonor = CaseDonor::factory()->create([
        'case_id' => $this->case->id,
        'donor_id' => Donor::factory()->create([
            'donor_no' => 'D2611000001',
            'name' => 'Karim Mia',
            'age' => 32,
            'date_of_birth' => null,
            'gender' => Gender::Male,
            'blood_group' => BloodGroup::BPositive,
        ]),
        'status' => CaseDonorStatus::Collected,
    ]);
    $this->bag = Collection::factory()->create(['case_donor_id' => $this->caseDonor->id, 'bag_no' => 'B2611020001']);

    $this->unit = fn (ComponentType $type, bool $forPatient) => Unit::factory()->create([
        'branch_id' => $this->branch->id,
        'collection_id' => $this->bag->id,
        'component_type_id' => $type->id,
        'case_id' => $forPatient ? $this->case->id : null,
        'blood_group' => BloodGroup::BPositive,
        'status' => $forPatient ? UnitStatus::Reserved : UnitStatus::Available,
        'storage_location_id' => $forPatient ? null : $this->freezer->id,
    ]);

    $this->print = fn (Unit $unit, array $payload = []) => signInToBranch($this, $this->technologist, $this->branch)
        ->postJson("/api/blood-bank/cases/{$this->case->id}/unit-labels", ['unit_ids' => [$unit->id], ...$payload]);
});

it('reprints a patient unit label with the donor screening and requires a reason for a reprint', function () {
    $sample = Sample::factory()->create(['case_id' => $this->case->id, 'case_donor_id' => $this->caseDonor->id, 'subject_type' => SampleSubject::Donor]);
    TestResult::factory()->create([
        'sample_id' => $sample->id,
        'test_definition_id' => TestDefinition::where('code', 'MALARIAL_PARASITE')->value('id'),
        'result_value' => 'not_found',
    ]);
    $unit = ($this->unit)($this->pcv, true);

    $response = ($this->print)($unit)
        ->assertOk()
        ->assertJsonPath('data.0.unit_no', 'BC2611020001')
        ->assertJsonPath('data.0.donor_no', 'D2611000001')
        ->assertJsonPath('data.0.screening.0.code', 'MALARIAL_PARASITE')
        ->assertJsonPath('data.0.screening.0.result', 'not_found')
        ->assertJsonPath('data.0.reserved_for.patient_no', 'P2611020001')
        ->assertJsonPath('data.0.label_print.is_reprint', false);

    expect($response->json('data.0.label_command'))
        ->toContain('BQ,42,63,2,5,64,0,1,BC2611020001')
        ->toContain(',0,Rahim Uddin')
        ->toContain(',0,B+ve')
        ->toContain(',0,PCV');

    ($this->print)($unit)->assertUnprocessable()->assertJson(['code' => 'REPRINT_REASON_REQUIRED']);

    ($this->print)($unit, ['reprint_reason' => 'Label wet'])->assertOk()->assertJsonPath('data.0.label_print.is_reprint', true);
});

it('prints the storage label for a stock unit, with the donor instead of a patient', function () {
    $unit = ($this->unit)($this->ffp, false);

    $command = ($this->print)($unit)->assertOk()->assertJsonPath('data.0.reserved_for', null)->json('data.0.label_command');

    expect($command)
        ->toContain('BQ,37,62,2,6,60,0,3,BF2611020001')
        ->toContain('BQ,544,62,2,6,60,0,3,D2611000001')
        ->toContain(',0E,Karim Mia')
        ->toContain(',0E,32Y')
        ->toContain(',0E,Male')
        ->toContain(',0E,FFP')
        ->not->toContain('Rahim Uddin');
});

it('does not print a label for a unit of another case', function () {
    signInToBranch($this, $this->technologist, $this->branch)
        ->postJson("/api/blood-bank/cases/{$this->case->id}/unit-labels", ['unit_ids' => [Unit::factory()->create()->id]])
        ->assertUnprocessable()
        ->assertJson(['code' => 'UNIT_NOT_IN_CASE']);
});

it('lists this branch\'s stock on the units tab', function () {
    ($this->unit)($this->pcv, true);
    ($this->unit)($this->ffp, false);
    Unit::factory()->create(['status' => UnitStatus::Available]);

    signInToBranch($this, $this->technologist, $this->branch)
        ->getJson('/api/blood-bank/units?filter[status]=available')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.unit_no', 'BF2611020001')
        ->assertJsonPath('data.0.bag_case_id', $this->case->id)
        ->assertJsonPath('data.0.storage_location.id', $this->freezer->id);
});
