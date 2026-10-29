<?php

use Modules\BloodBank\Enums\BloodBankPermission;
use Modules\BloodBank\Models\Donor;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->staff = User::factory()->create();
    grantInBranch($this->staff, $this->branch, BloodBankPermission::DonorView);
});

it('ages a donor registered without a date of birth forward from the registration date', function () {
    $this->travelTo(now()->setDate(2025, 10, 1));
    $donor = Donor::factory()->create(['phone' => '01911000000', 'date_of_birth' => null, 'age' => 28]);
    Donor::factory()->create(['phone' => '019110000001']);
    $this->travelTo(now()->setDate(2026, 10, 29));

    signInToBranch($this, $this->staff, $this->branch)
        ->getJson('/api/blood-bank/donors/lookup?phone=01911000000')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $donor->id)
        ->assertJsonPath('data.0.age', 29);
});
