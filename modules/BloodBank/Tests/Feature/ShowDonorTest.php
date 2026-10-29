<?php

use Modules\BloodBank\Enums\BloodBankPermission;
use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\Collection;
use Modules\BloodBank\Models\Donor;
use Modules\BloodBank\Models\PatientCase;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Patient\Models\PatientMaster;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->otherBranch = Branch::factory()->create();
    $this->staff = User::factory()->create();
    grantInBranch($this->staff, $this->branch, BloodBankPermission::DonorView);
});

it('shows the donor\'s donations across all branches, newest first, with the patient and the bag', function () {
    $donor = Donor::factory()->create();

    $this->travelTo(now()->subMonths(6));
    $deferredFor = PatientMaster::factory()->create(['name' => 'Ayesha']);
    CaseDonor::factory()->create([
        'donor_id' => $donor->id,
        'case_id' => PatientCase::factory()->create(['branch_id' => $this->otherBranch->id, 'patient_master_id' => $deferredFor->id]),
        'status' => CaseDonorStatus::Deferred,
        'deferral_reason' => 'Low haemoglobin',
    ]);
    $this->travelBack();

    $donatedFor = PatientMaster::factory()->create(['name' => 'Rahim']);
    $donation = CaseDonor::factory()->create([
        'donor_id' => $donor->id,
        'case_id' => PatientCase::factory()->create(['branch_id' => $this->branch->id, 'patient_master_id' => $donatedFor->id]),
        'status' => CaseDonorStatus::Collected,
    ]);
    Collection::factory()->create(['case_donor_id' => $donation->id, 'bag_no' => 'BB-B-000123']);
    CaseDonor::factory()->create();

    signInToBranch($this, $this->staff, $this->branch)
        ->getJson("/api/blood-bank/donors/{$donor->id}")
        ->assertOk()
        ->assertJsonPath('data.donor_no', $donor->donor_no)
        ->assertJsonCount(2, 'data.donations')
        ->assertJsonPath('data.donations.0.status', 'collected')
        ->assertJsonPath('data.donations.0.patient.name', 'Rahim')
        ->assertJsonPath('data.donations.0.collections.0.bag_no', 'BB-B-000123')
        ->assertJsonPath('data.donations.1.status', 'deferred')
        ->assertJsonPath('data.donations.1.deferral_reason', 'Low haemoglobin')
        ->assertJsonPath('data.donations.1.case.branch_id', $this->otherBranch->id)
        ->assertJsonPath('data.donations.1.patient.name', 'Ayesha')
        ->assertJsonCount(0, 'data.donations.1.collections');
});
