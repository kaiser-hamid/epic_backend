<?php

use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Enums\BloodBankPermission;
use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Enums\CaseService;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Enums\CollectionStatus;
use Modules\BloodBank\Enums\UnitStatus;
use Modules\BloodBank\Models\CaseComponent;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\Collection;
use Modules\BloodBank\Models\ComponentType;
use Modules\BloodBank\Models\Donor;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\StorageLocation;
use Modules\BloodBank\Models\Unit;
use Modules\Shared\Audit\Models\AuditLog;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Patient\Models\PatientMaster;
use Modules\Shared\Printing\Models\PrintLog;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->phlebotomist = User::factory()->create(['name' => 'Salma Akter']);
    grantInBranch(
        $this->phlebotomist,
        $this->branch,
        BloodBankPermission::CollectionView,
        BloodBankPermission::CollectionCollect,
    );

    $this->types = collect([
        ['code' => 'WB', 'unit_prefix' => 'BH', 'default_location_type' => 'refrigerator'],
        ['code' => 'PA', 'unit_prefix' => 'BA', 'default_location_type' => 'refrigerator'],
        ['code' => 'PCV', 'unit_prefix' => 'BC', 'default_location_type' => 'refrigerator'],
        ['code' => 'FP', 'unit_prefix' => 'BP', 'default_location_type' => 'freezer'],
        ['code' => 'FFP', 'unit_prefix' => 'BF', 'default_location_type' => 'freezer'],
        ['code' => 'PLATELET', 'unit_prefix' => 'BT', 'default_location_type' => 'platelet_agitator'],
        ['code' => 'CRYO', 'unit_prefix' => 'BR', 'default_location_type' => 'freezer'],
        ['code' => 'PRP', 'unit_prefix' => 'BQ', 'default_location_type' => 'platelet_agitator'],
        ['code' => 'PLASMA', 'unit_prefix' => 'BS', 'default_location_type' => 'freezer'],
    ])->mapWithKeys(fn (array $type): array => [$type['code'] => ComponentType::factory()->create(['shelf_life_hours' => 35 * 24, ...$type])]);
    $this->freezer = StorageLocation::factory()->create(['branch_id' => $this->branch->id]);

    $this->case = PatientCase::factory()->create([
        'branch_id' => $this->branch->id,
        'billing_id' => Billing::factory()->create(['invoice_no' => 'NB2611020001'])->id,
        'patient_master_id' => PatientMaster::factory()->create(['name' => 'Rahim Uddin', 'patient_no' => 'P2611020001'])->id,
    ]);
    $this->bill = fn (array $quantities) => collect($quantities)->each(fn (int $quantity, string $code) => CaseComponent::factory()->create([
        'case_id' => $this->case->id,
        'component_type_id' => $this->types[$code]->id,
        'quantity' => $quantity,
    ]));
    ($this->bill)(['WB' => 1]);
    $this->donor = CaseDonor::factory()->create([
        'case_id' => $this->case->id,
        'donor_id' => Donor::factory()->create(['name' => 'Karim Mia', 'donor_no' => 'D2611000001']),
        'sequence_no' => 1,
        'status' => CaseDonorStatus::Eligible,
    ]);

    $this->collect = fn (?CaseDonor $caseDonor = null, array $payload = [], ?PatientCase $case = null) => signInToBranch($this, $this->phlebotomist, $this->branch)
        ->postJson(sprintf('/api/blood-bank/cases/%d/donors/%d/collection', ($case ?? $this->case)->id, ($caseDonor ?? $this->donor)->id), $payload);
});

it('collects blood from an eligible donor into a bag with a daily bag number', function () {
    $this->travelTo(now()->setDate(2026, 11, 2));

    ($this->collect)(payload: ['notes' => 'Smooth donation'])
        ->assertCreated()
        ->assertJsonPath('data.donors.0.case_donor_status', 'collected')
        ->assertJsonPath('data.donors.0.is_collectable', false)
        ->assertJsonPath('data.donors.0.collection.bag_no', 'B2611020001')
        ->assertJsonPath('data.collected_units.0.volume_ml', null)
        ->assertJsonPath('data.donors.0.collection.collected_by.name', 'Salma Akter');

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

it('plans each bag by what the case still needs: the needed components for the patient, the rest to stock', function (array $billed, array $patient, array $stock, bool $keepsInStorage = false) {
    CaseComponent::query()->delete();
    ($this->bill)($billed);
    $this->case->update(['keeps_units_in_storage' => $keepsInStorage]);

    $plan = signInToBranch($this, $this->phlebotomist, $this->branch)
        ->getJson("/api/blood-bank/cases/{$this->case->id}/blood-collection")
        ->assertOk()
        ->json('data.bag_plan');

    $rows = collect($plan);

    expect($rows->where('for_patient', true)->pluck('code')->all())->toBe($patient)
        ->and($rows->where('for_patient', false)->pluck('code')->values()->all())->toBe($stock)
        ->and($rows->every(fn (array $row): bool => $row['needs_storage_location'] === (! $row['for_patient'] || $keepsInStorage)))->toBeTrue();
})->with([
    'whole blood' => [['WB' => 1], ['WB'], []],
    'platelet apheresis' => [['PA' => 1], ['PA'], []],
    'PCV' => [['PCV' => 1], ['PCV'], ['FFP']],
    'FFP' => [['FFP' => 1], ['FFP'], ['PCV']],
    'FP' => [['FP' => 1], ['FP'], ['PCV']],
    'PCV and FP' => [['PCV' => 1, 'FP' => 1], ['PCV', 'FP'], []],
    'platelet' => [['PLATELET' => 4], ['PLATELET'], ['PCV', 'FFP']],
    'PCV and platelet' => [['PCV' => 1, 'PLATELET' => 1], ['PCV', 'PLATELET'], ['FFP']],
    'cryo' => [['CRYO' => 1], ['CRYO'], []],
    'PRP' => [['PRP' => 1], ['PRP'], []],
    'cryo poor plasma' => [['PLASMA' => 1], ['PLASMA'], []],
    'PCV kept in storage (SWPF01)' => [['PCV' => 1], ['PCV'], ['FFP'], true],
]);

it('makes the bag into its patient and stock units at collection and returns their labels, logged as printed', function () {
    $this->travelTo(now()->setDate(2026, 11, 2));
    CaseComponent::query()->delete();
    ($this->bill)(['PCV' => 1]);

    $response = ($this->collect)(payload: [
        'units' => [
            ['component_type_id' => $this->types['PCV']->id, 'volume_ml' => 250],
            ['component_type_id' => $this->types['FFP']->id, 'storage_location_id' => $this->freezer->id, 'volume_ml' => 200],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.bag_plan', [])
        ->assertJsonCount(2, 'data.collected_units')
        ->assertJsonPath('data.collected_units.0.unit_no', 'BC2611020001')
        ->assertJsonPath('data.collected_units.0.status', 'reserved')
        ->assertJsonPath('data.collected_units.0.reserved_for.case_id', $this->case->id)
        ->assertJsonPath('data.collected_units.0.volume_ml', 250)
        ->assertJsonPath('data.collected_units.1.unit_no', 'BF2611020001')
        ->assertJsonPath('data.collected_units.1.status', 'available')
        ->assertJsonPath('data.collected_units.1.storage_location.id', $this->freezer->id)
        ->assertJsonPath('data.collected_units.1.label_print.is_reprint', false);

    expect($response->json('data.collected_units.0.label_command'))->toContain(',0,Rahim Uddin')
        ->and($response->json('data.collected_units.1.label_command'))->toContain('D2611000001')->not->toContain('Rahim Uddin')
        ->and(PrintLog::where('document_type', 'bb.patient_label')->count())->toBe(1)
        ->and(PrintLog::where('document_type', 'bb.storage_label')->count())->toBe(1)
        ->and(Unit::where('collection_id', Collection::sole()->id)->count())->toBe(2);
});

it('needs a storage location for a stock unit and accepts only the units of the plan', function (Closure $units, string $field) {
    CaseComponent::query()->delete();
    ($this->bill)(['PCV' => 1]);

    ($this->collect)(payload: ['units' => $units->call($this)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(Collection::count())->toBe(0)->and(Unit::count())->toBe(0);
})->with([
    'stock unit left out' => [fn () => [['component_type_id' => $this->types['PCV']->id]], 'units'],
    'stock unit without a location' => [fn () => [['component_type_id' => $this->types['FFP']->id]], 'units.0.storage_location_id'],
    'another branch\'s location' => [fn () => [['component_type_id' => $this->types['FFP']->id, 'storage_location_id' => StorageLocation::factory()->create()->id]], 'units.0.storage_location_id'],
    'a component not made from this bag' => [fn () => [['component_type_id' => $this->types['PLATELET']->id]], 'units.0.component_type_id'],
]);

it('collects no more blood once the case holds everything it was billed for', function () {
    Unit::factory()->create(['case_id' => $this->case->id, 'component_type_id' => $this->types['WB']->id, 'status' => UnitStatus::Reserved]);

    signInToBranch($this, $this->phlebotomist, $this->branch)
        ->getJson("/api/blood-bank/cases/{$this->case->id}/blood-collection")
        ->assertJsonPath('data.bag_plan', [])
        ->assertJsonPath('data.donors.0.is_collectable', false);

    ($this->collect)()->assertUnprocessable()->assertJson(['code' => 'CASE_NEEDS_NOTHING_MORE']);

    expect($this->donor->collections()->count())->toBe(0);
});

it('lists open cases with a donor cleared to donate and a unit still needed as pending', function () {
    $waitingForSerology = PatientCase::factory()->create(['branch_id' => $this->branch->id]);
    CaseDonor::factory()->create(['case_id' => $waitingForSerology->id, 'status' => CaseDonorStatus::Fit]);
    $alreadyCollected = PatientCase::factory()->create(['branch_id' => $this->branch->id]);
    CaseDonor::factory()->create(['case_id' => $alreadyCollected->id, 'status' => CaseDonorStatus::Collected]);
    $holdsEverything = PatientCase::factory()->create(['branch_id' => $this->branch->id]);
    CaseComponent::factory()->create(['case_id' => $holdsEverything->id, 'component_type_id' => $this->types['PCV']->id, 'quantity' => 1]);
    Unit::factory()->create(['case_id' => $holdsEverything->id, 'component_type_id' => $this->types['PCV']->id, 'status' => UnitStatus::Reserved]);
    CaseDonor::factory()->create(['case_id' => $holdsEverything->id, 'status' => CaseDonorStatus::Eligible]);

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

it('records a therapeutic phlebotomy with its vitals and closes the case', function () {
    grantInBranch($this->phlebotomist, $this->branch, BloodBankPermission::PhlebotomyRecord);
    $this->donor->delete();
    $this->case->update(['service' => CaseService::TherapeuticPhlebotomy]);

    signInToBranch($this, $this->phlebotomist, $this->branch)
        ->getJson('/api/blood-bank/blood-collection/pending')
        ->assertOk()
        ->assertJsonPath('data.0.id', $this->case->id);

    signInToBranch($this, $this->phlebotomist, $this->branch)
        ->postJson("/api/blood-bank/cases/{$this->case->id}/therapeutic-phlebotomy", [
            'volume_ml' => 450,
            'pre' => ['pulse_bpm' => 80, 'bp_systolic' => 130, 'bp_diastolic' => 85, 'temperature_f' => 98.4],
            'notes' => 'Hb 18.2',
        ])
        ->assertOk()
        ->assertJsonPath('data.case.status', 'closed')
        ->assertJsonPath('data.can_record_phlebotomy', false)
        ->assertJsonPath('data.phlebotomy.volume_ml', 450)
        ->assertJsonPath('data.phlebotomy.pre.bp_systolic', 130)
        ->assertJsonPath('data.phlebotomy.post', null)
        ->assertJsonPath('data.phlebotomy.performed_by.name', 'Salma Akter');

    expect(AuditLog::where('action', BloodBankAuditAction::PhlebotomyRecorded)->exists())->toBeTrue()
        ->and(AuditLog::where('action', BloodBankAuditAction::CaseClosed)->sole()->new_values['via'])->toBe('therapeutic_phlebotomy');
});

it('records a therapeutic phlebotomy only on an open Therapeutic Phlebotomy case', function () {
    grantInBranch($this->phlebotomist, $this->branch, BloodBankPermission::PhlebotomyRecord);
    $record = fn () => signInToBranch($this, $this->phlebotomist, $this->branch)
        ->postJson("/api/blood-bank/cases/{$this->case->id}/therapeutic-phlebotomy", ['volume_ml' => 450]);

    $record()->assertUnprocessable()->assertJson(['code' => 'NOT_A_PHLEBOTOMY_CASE']);

    $this->case->update(['service' => CaseService::TherapeuticPhlebotomy, 'status' => CaseStatus::Closed]);
    $record()->assertUnprocessable()->assertJson(['code' => 'CASE_NOT_OPEN']);
});
