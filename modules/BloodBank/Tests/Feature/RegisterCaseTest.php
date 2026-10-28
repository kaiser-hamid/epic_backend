<?php

use Illuminate\Testing\TestResponse;
use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Enums\BloodBankPermission;
use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\ComponentType;
use Modules\BloodBank\Models\Donor;
use Modules\BloodBank\Models\PatientCase;
use Modules\Shared\Audit\Models\AuditLog;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Patient\Models\PatientMaster;
use Modules\Shared\TestCatalog\Models\TestItem;
use Tests\TestCase;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->executive = User::factory()->create();
    grantInBranch($this->executive, $this->branch, BloodBankPermission::CaseCreate);
});

/**
 * @param  array<string, mixed>  $payload
 */
function registerCase(TestCase $test, array $payload): TestResponse
{
    return signInToBranch($test, $test->executive, $test->branch)->postJson('/api/blood-bank/cases', $payload);
}

/**
 * A valid form for a returning patient, with overrides merged in.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function caseForm(array $overrides = []): array
{
    return [
        'billing' => [
            'patient_id' => PatientMaster::factory()->create()->id,
            'items' => [['test_item_id' => TestItem::factory()->create()->id]],
            'referral' => ['is_self_referral' => true],
        ],
        'care_type' => 'daycare',
        ...$overrides,
    ];
}

it('bills a new patient and opens the case with components and donors from one form', function () {
    $this->travelTo(now()->setDate(2026, 10, 28));
    $pcv = ComponentType::factory()->create(['code' => 'PCV']);
    $returningDonor = Donor::factory()->create();

    $response = registerCase($this, [
        'billing' => [
            'patient' => ['name' => 'Rahim Uddin', 'age_years' => 40, 'gender' => 'male', 'phone' => '01711000000'],
            'items' => [['test_item_id' => TestItem::factory()->create(['rate' => 150000])->id]],
            'referral' => ['is_self_referral' => true],
        ],
        'care_type' => 'daycare',
        'transfusion_reason' => 'Thalassemia',
        'components' => [['component_type_id' => $pcv->id, 'quantity' => 2]],
        'donors' => [
            ['donor_id' => $returningDonor->id],
            ['donor' => ['name' => 'Karim Mia', 'age' => 28, 'blood_group' => 'O_POS', 'is_regular_volunteer' => true]],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.case_no', 'BC2610000001')
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.invoice.invoice_no', "{$this->branch->code}2610280001")
        ->assertJsonPath('data.invoice.amount', 150000)
        ->assertJsonPath('data.patient.patient_no', 'P2610280001')
        ->assertJsonPath('data.patient.name', 'Rahim Uddin')
        ->assertJsonPath('data.components.0.code', 'PCV')
        ->assertJsonPath('data.components.0.quantity', 2)
        ->assertJsonPath('data.donors.0.sequence_no', 1)
        ->assertJsonPath('data.donors.0.donor.id', $returningDonor->id)
        ->assertJsonPath('data.donors.1.sequence_no', 2)
        ->assertJsonPath('data.donors.1.donor.donor_no', 'D2610000001');

    $case = PatientCase::sole();
    expect($case->branch_id)->toBe($this->branch->id)
        ->and($case->billing_id)->toBe(Billing::sole()->id)
        ->and(CaseDonor::pluck('status')->all())->toBe([CaseDonorStatus::Registered, CaseDonorStatus::Registered])
        ->and(AuditLog::where('action', BloodBankAuditAction::CaseRegistered)->sole()->auditable_type)->toBe('bb.case')
        ->and(AuditLog::where('action', BloodBankAuditAction::DonorRegistered)->count())->toBe(1);
});

it('restarts the case number every month', function () {
    $this->travelTo(now()->setDate(2026, 10, 31));
    registerCase($this, caseForm())->assertJsonPath('data.case_no', 'BC2610000001');
    registerCase($this, caseForm())->assertJsonPath('data.case_no', 'BC2610000002');

    $this->travelTo(now()->setDate(2026, 11, 1));
    registerCase($this, caseForm())->assertJsonPath('data.case_no', 'BC2611000001');
});

it('returns 422 VALIDATION_FAILED and saves nothing for an invalid form', function (Closure $overrides, string $field) {
    registerCase($this, caseForm($overrides()))
        ->assertUnprocessable()
        ->assertJson(['code' => 'VALIDATION_FAILED'])
        ->assertJsonValidationErrors([$field]);

    expect(PatientCase::count())->toBe(0)
        ->and(Billing::count())->toBe(0);
})->with([
    'bill without a patient' => [fn () => ['billing' => [
        'items' => [['test_item_id' => TestItem::factory()->create()->id]],
        'referral' => ['is_self_referral' => true],
    ]], 'billing.patient_id'],
    'unknown care type' => [fn () => ['care_type' => 'home'], 'care_type'],
    'inactive component type' => [fn () => [
        'components' => [['component_type_id' => ComponentType::factory()->create(['is_active' => false])->id]],
    ], 'components.0.component_type_id'],
    'same component twice' => [function () {
        $componentType = ComponentType::factory()->create();

        return ['components' => [['component_type_id' => $componentType->id], ['component_type_id' => $componentType->id]]];
    }, 'components.0.component_type_id'],
    'donor with neither donor_id nor donor' => [fn () => ['donors' => [[]]], 'donors.0.donor_id'],
    'donor with both donor_id and donor' => [fn () => ['donors' => [[
        'donor_id' => Donor::factory()->create()->id,
        'donor' => ['name' => 'Karim Mia', 'age' => 28],
    ]]], 'donors.0.donor_id'],
    'same donor twice' => [function () {
        $donor = Donor::factory()->create();

        return ['donors' => [['donor_id' => $donor->id], ['donor_id' => $donor->id]]];
    }, 'donors.0.donor_id'],
    'new donor without age or date of birth' => [fn () => ['donors' => [['donor' => ['name' => 'Karim Mia']]]], 'donors.0.donor.age'],
]);

it('returns 403 FORBIDDEN without the case registration permission', function () {
    $viewer = User::factory()->create();
    grantInBranch($viewer, $this->branch, BloodBankPermission::CaseView);

    signInToBranch($this, $viewer, $this->branch)
        ->postJson('/api/blood-bank/cases', caseForm())
        ->assertForbidden()
        ->assertJson(['code' => 'FORBIDDEN']);
});
