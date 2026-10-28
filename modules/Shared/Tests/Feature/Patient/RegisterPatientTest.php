<?php

use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Patient\Models\PatientMaster;
use Modules\Shared\Rbac\Enums\SharedPermission;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->staff = User::factory()->create();
    grantInBranch($this->staff, $this->branch, SharedPermission::PatientCreate, SharedPermission::PatientView);
});

it('derives the age from the date of birth, ignoring a typed age', function () {
    $this->travelTo(now()->setDate(2026, 10, 28));

    signInToBranch($this, $this->staff, $this->branch)
        ->postJson('/api/patients', ['name' => 'Ayesha', 'date_of_birth' => '2020-08-15', 'age_years' => 99])
        ->assertCreated()
        ->assertJsonPath('data.patient_no', 'P2610280001')
        ->assertJsonPath('data.age_years', 6)
        ->assertJsonPath('data.age_months', 2)
        ->assertJsonPath('data.age_days', 13);
});

it('back-calculates the date of birth from a typed age', function () {
    $this->travelTo(now()->setDate(2026, 10, 28));

    signInToBranch($this, $this->staff, $this->branch)
        ->postJson('/api/patients', ['name' => 'Ayesha', 'age_years' => 6, 'age_months' => 2, 'age_days' => 13])
        ->assertCreated()
        ->assertJsonPath('data.date_of_birth', '2020-08-15')
        ->assertJsonPath('data.age_years', 6);
});

it('returns 422 VALIDATION_FAILED when neither age nor date of birth is given', function () {
    signInToBranch($this, $this->staff, $this->branch)
        ->postJson('/api/patients', ['name' => 'Ayesha'])
        ->assertUnprocessable()
        ->assertJson(['code' => 'VALIDATION_FAILED'])
        ->assertJsonValidationErrors(['age_years']);

    expect(PatientMaster::count())->toBe(0);
});

it('finds patients registered in any branch by phone or patient no', function () {
    $patient = PatientMaster::factory()->create(['patient_no' => 'P2610000042', 'phone' => '01911000000']);
    PatientMaster::factory()->create();

    foreach (['01911000000', 'P2610000042'] as $search) {
        signInToBranch($this, $this->staff, $this->branch)
            ->getJson("/api/patients?filter[search]={$search}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $patient->id);
    }
});
