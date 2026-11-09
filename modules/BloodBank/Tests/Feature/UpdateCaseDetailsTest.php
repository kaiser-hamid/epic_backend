<?php

use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Enums\BloodBankPermission;
use Modules\BloodBank\Enums\CareType;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Models\PatientCase;
use Modules\Shared\Audit\Models\AuditLog;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->executive = User::factory()->create();
    grantInBranch($this->executive, $this->branch, BloodBankPermission::CaseUpdate);
    $this->case = PatientCase::factory()->create(['branch_id' => $this->branch->id, 'care_type' => CareType::Out]);

    $this->update = fn (array $payload, ?PatientCase $case = null) => signInToBranch($this, $this->executive, $this->branch)
        ->patchJson('/api/blood-bank/cases/'.($case ?? $this->case)->id, $payload);
});

it('corrects "Care of" and records the cause of transfusion, auditing only what changed', function () {
    ($this->update)(['care_type' => 'daycare', 'transfusion_reason' => 'Thalassaemia'])
        ->assertOk()
        ->assertJsonPath('data.care_type', 'daycare')
        ->assertJsonPath('data.transfusion_reason', 'Thalassaemia');

    $audit = AuditLog::where('action', BloodBankAuditAction::CaseDetailsUpdated)->sole();

    expect($audit->old_values)->toBe(['care_type' => 'out', 'transfusion_reason' => null])
        ->and($audit->new_values)->toBe(['care_type' => 'daycare', 'transfusion_reason' => 'Thalassaemia']);

    ($this->update)(['care_type' => 'daycare'])->assertOk();

    expect(AuditLog::where('action', BloodBankAuditAction::CaseDetailsUpdated)->count())->toBe(1);
});

it('refuses a closed case and another branch\'s case', function () {
    $this->case->update(['status' => CaseStatus::Closed]);

    ($this->update)(['reference_note' => 'Late'])->assertUnprocessable()->assertJson(['code' => 'CASE_NOT_OPEN']);
    ($this->update)(['reference_note' => 'Late'], PatientCase::factory()->create())->assertForbidden();
});
