<?php

use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Enums\BloodBankPermission;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Enums\UnitStatus;
use Modules\BloodBank\Models\CaseComponent;
use Modules\BloodBank\Models\ComponentType;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Unit;
use Modules\Shared\Audit\Models\AuditLog;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->executive = User::factory()->create(['name' => 'Nasrin Akter']);
    grantInBranch($this->executive, $this->branch, BloodBankPermission::CaseView, BloodBankPermission::CaseClose);
    $this->case = PatientCase::factory()->create(['branch_id' => $this->branch->id]);

    $this->close = fn (array $payload = []) => signInToBranch($this, $this->executive, $this->branch)
        ->postJson("/api/blood-bank/cases/{$this->case->id}/close", $payload);
    $this->show = fn () => signInToBranch($this, $this->executive, $this->branch)
        ->getJson("/api/blood-bank/cases/{$this->case->id}");
});

it('closes a case billed for services only without a reason', function () {
    ($this->show)()->assertOk()->assertJsonPath('data.closing', ['can_close' => true, 'needs_reason' => false]);

    ($this->close)()
        ->assertOk()
        ->assertJsonPath('data.status', 'closed')
        ->assertJsonPath('data.closed_by.name', 'Nasrin Akter')
        ->assertJsonPath('data.closing.can_close', false);

    expect(AuditLog::where('action', BloodBankAuditAction::CaseClosed)->sole()->new_values)
        ->toMatchArray(['via' => 'close_case', 'close_reason' => null, 'outstanding' => []]);
});

it('needs a reason to close a case with billed components still outstanding, and records them', function () {
    CaseComponent::factory()->create([
        'case_id' => $this->case->id,
        'component_type_id' => ComponentType::factory()->create(['code' => 'PCV'])->id,
        'quantity' => 2,
    ]);

    ($this->show)()->assertJsonPath('data.closing', ['can_close' => true, 'needs_reason' => true]);
    ($this->close)()->assertUnprocessable()->assertJson(['code' => 'CLOSE_REASON_REQUIRED']);

    ($this->close)(['reason' => 'Patient left against medical advice'])
        ->assertOk()
        ->assertJsonPath('data.close_reason', 'Patient left against medical advice');

    expect($this->case->fresh()->status)->toBe(CaseStatus::Closed)
        ->and(AuditLog::where('action', BloodBankAuditAction::CaseClosed)->sole()->new_values['outstanding'])->toBe(['PCV' => 2]);
});

it('refuses to close a case while a unit is still reserved for it', function () {
    Unit::factory()->create(['case_id' => $this->case->id, 'branch_id' => $this->branch->id, 'status' => UnitStatus::Reserved]);

    ($this->show)()->assertJsonPath('data.closing.can_close', false);
    ($this->close)(['reason' => 'Patient left'])->assertConflict()->assertJson(['code' => 'CASE_HAS_RESERVED_UNITS']);

    expect($this->case->fresh()->status)->toBe(CaseStatus::Open);
});

it('refuses a closed case and another branch\'s case', function () {
    $this->case->update(['status' => CaseStatus::Closed]);
    ($this->close)()->assertUnprocessable()->assertJson(['code' => 'CASE_NOT_OPEN']);

    $this->case = PatientCase::factory()->create();
    ($this->close)()->assertForbidden();
});
