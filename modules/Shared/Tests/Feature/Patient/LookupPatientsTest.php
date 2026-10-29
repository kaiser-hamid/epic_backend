<?php

use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Patient\Models\PatientMaster;
use Modules\Shared\Rbac\Enums\SharedPermission;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->staff = User::factory()->create();
    grantInBranch($this->staff, $this->branch, SharedPermission::PatientView);
});

it('finds patients by exact phone with the age as of today, not as registered', function () {
    $this->travelTo(now()->setDate(2026, 10, 29));
    $patient = PatientMaster::factory()->create([
        'phone' => '01711000000',
        'date_of_birth' => '1986-08-19',
        'age_years' => 38,
        'age_months' => 0,
        'age_days' => 0,
    ]);
    PatientMaster::factory()->create(['phone' => '017110000001']);

    signInToBranch($this, $this->staff, $this->branch)
        ->getJson('/api/patients/lookup?phone=01711000000')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $patient->id)
        ->assertJsonPath('data.0.age_years', 40)
        ->assertJsonPath('data.0.age_months', 2)
        ->assertJsonPath('data.0.age_days', 10);
});

it('returns 422 VALIDATION_FAILED on phone when it is missing', function () {
    signInToBranch($this, $this->staff, $this->branch)
        ->getJson('/api/patients/lookup')
        ->assertUnprocessable()
        ->assertJson(['code' => 'VALIDATION_FAILED'])
        ->assertJsonValidationErrors(['phone']);
});
