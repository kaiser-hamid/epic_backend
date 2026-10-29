<?php

use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Doctor\Models\Doctor;
use Modules\Shared\TestCatalog\Models\TestItem;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->staff = User::factory()->create(['name' => 'Counter Staff', 'employee_id' => 'EMP-0001']);
});

it('lists active doctors matching the search, an exact code first', function () {
    $partialCode = Doctor::factory()->create(['code' => 'DR-123', 'name' => 'Dr. Anwar']);
    $exactCode = Doctor::factory()->create(['code' => 'DR-12', 'name' => 'Dr. Zahid']);
    Doctor::factory()->create(['code' => 'DR-120', 'name' => 'Dr. Babul', 'is_active' => false]);

    $response = signInToBranch($this, $this->staff, $this->branch)
        ->getJson('/api/doctors/lookup?search=dr-12')
        ->assertOk();

    expect($response->json('data.*.id'))->toBe([$exactCode->id, $partialCode->id]);
});

it('lists active test items matching the code or the name', function () {
    $byCode = TestItem::factory()->create(['code' => 'CBC', 'name' => 'Complete Blood Count']);
    $byName = TestItem::factory()->create(['code' => 'H-01', 'name' => 'CBC with ESR']);
    TestItem::factory()->create(['code' => 'CBC-OLD', 'is_active' => false]);

    $response = signInToBranch($this, $this->staff, $this->branch)
        ->getJson('/api/test-items/lookup?search=cbc')
        ->assertOk();

    expect($response->json('data.*.id'))->toBe([$byCode->id, $byName->id]);
});

it('leaves out the test items already picked on the form', function () {
    $picked = TestItem::factory()->create(['code' => 'CBC', 'name' => 'Complete Blood Count']);
    $other = TestItem::factory()->create(['code' => 'H-01', 'name' => 'CBC with ESR']);

    $response = signInToBranch($this, $this->staff, $this->branch)
        ->getJson("/api/test-items/lookup?search=cbc&exclude_ids[]={$picked->id}")
        ->assertOk();

    expect($response->json('data.*.id'))->toBe([$other->id]);
});

it('lists only active staff matching the search', function () {
    $active = User::factory()->create(['name' => 'Rahim Uddin']);
    User::factory()->inactive()->create(['name' => 'Rahim Mia']);

    signInToBranch($this, $this->staff, $this->branch)
        ->getJson('/api/staff/lookup?search=rahim')
        ->assertOk()
        ->assertExactJson(['data' => [[
            'id' => $active->id,
            'employee_id' => $active->employee_id,
            'name' => 'Rahim Uddin',
            'designation' => $active->designation,
        ]]]);
});

it('returns 422 VALIDATION_FAILED on search when it is missing', function () {
    signInToBranch($this, $this->staff, $this->branch)
        ->getJson('/api/doctors/lookup')
        ->assertUnprocessable()
        ->assertJson(['code' => 'VALIDATION_FAILED'])
        ->assertJsonValidationErrors(['search']);
});

it('returns 401 without a session', function () {
    $this->withHeader('Referer', 'http://localhost')
        ->getJson('/api/staff/lookup?search=rahim')
        ->assertUnauthorized();
});
