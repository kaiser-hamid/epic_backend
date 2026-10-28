<?php

use Modules\BloodBank\Enums\BloodBankPermission;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\Donor;
use Modules\BloodBank\Models\PatientCase;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Patient\Models\PatientMaster;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->otherBranch = Branch::factory()->create();
    $this->staff = User::factory()->create();
    grantInBranch($this->staff, $this->branch, BloodBankPermission::CaseView, BloodBankPermission::DonorView);
});

it('finds the session branch\'s case by its invoice no or a replacement donor\'s invoice no', function () {
    $caseBill = Billing::factory()->create(['invoice_no' => 'NB2610280001']);
    $replacementBill = Billing::factory()->create(['invoice_no' => 'NB2610280007']);
    $case = PatientCase::factory()->create(['branch_id' => $this->branch->id, 'billing_id' => $caseBill->id]);
    CaseDonor::factory()->create(['case_id' => $case->id, 'billing_id' => $replacementBill->id]);
    PatientCase::factory()->create(['branch_id' => $this->branch->id]);
    PatientCase::factory()->create(['branch_id' => $this->otherBranch->id, 'billing_id' => $caseBill->id]);

    foreach (['NB2610280001', 'NB2610280007'] as $invoiceNo) {
        signInToBranch($this, $this->staff, $this->branch)
            ->getJson("/api/blood-bank/cases?filter[invoice_no]={$invoiceNo}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $case->id)
            ->assertJsonPath('data.0.invoice.invoice_no', 'NB2610280001');
    }
});

it('finds cases by patient phone and shows the patient from the patient master', function () {
    $patient = PatientMaster::factory()->create(['phone' => '01811223344', 'name' => 'Ayesha']);
    $case = PatientCase::factory()->create(['branch_id' => $this->branch->id, 'patient_master_id' => $patient->id]);
    PatientCase::factory()->create(['branch_id' => $this->branch->id]);

    signInToBranch($this, $this->staff, $this->branch)
        ->getJson('/api/blood-bank/cases?filter[search]=01811223344')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $case->id)
        ->assertJsonPath('data.0.patient.name', 'Ayesha');
});

it('returns 403 FORBIDDEN for another branch\'s case even with the permission there', function () {
    grantInBranch($this->staff, $this->otherBranch, BloodBankPermission::CaseView);
    $foreign = PatientCase::factory()->create(['branch_id' => $this->otherBranch->id]);

    signInToBranch($this, $this->staff, $this->branch)
        ->getJson("/api/blood-bank/cases/{$foreign->id}")
        ->assertForbidden()
        ->assertJson(['code' => 'FORBIDDEN']);
});

it('finds a returning donor by phone', function () {
    $donor = Donor::factory()->create(['phone' => '01911000000']);
    Donor::factory()->create();

    signInToBranch($this, $this->staff, $this->branch)
        ->getJson('/api/blood-bank/donors?filter[search]=01911000000')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $donor->id);
});
