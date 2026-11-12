<?php

use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Audit\Models\AuditLog;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Rbac\Actions\SyncPermissionCatalog;
use Modules\Shared\Rbac\Contracts\AccessChecker;
use Modules\Shared\Rbac\Enums\SharedPermission;
use Modules\Shared\Rbac\Models\Permission;
use Modules\Shared\Rbac\Models\UserBranchPermission;

/*
 * Blood Bank ("blood-bank") is an optional module: only branches that run it may use or be given its
 * permissions. Every other module is available in every branch.
 */

beforeEach(function () {
    app(SyncPermissionCatalog::class)->handle();
    $this->bloodBankBranch = Branch::factory()->create();
    $this->plainBranch = Branch::factory()->create();
    enableModulesIn($this->bloodBankBranch, 'blood-bank');
});

it('refuses to grant a Blood Bank permission in a branch that does not run Blood Bank', function () {
    $admin = User::factory()->create();
    $staff = User::factory()->create();
    grantInBranch($admin, $this->plainBranch, SharedPermission::UserAssignPermissions);

    $save = fn (Branch $branch) => signInToBranch($this, $admin, $this->plainBranch)
        ->putJson("/api/access/users/{$staff->id}/branches/{$branch->id}/permissions", ['permissions' => ['bb.case.view', 'sh.billing.view']]);

    $save($this->plainBranch)
        ->assertUnprocessable()
        ->assertJson(['code' => 'MODULE_NOT_ENABLED_FOR_BRANCH']);
    expect(UserBranchPermission::where('user_id', $staff->id)->exists())->toBeFalse();

    $save($this->bloodBankBranch)->assertOk()->assertJsonPath('data.branch.modules', ['blood-bank']);
});

it('blocks Blood Bank in a branch without it, even for a leftover grant or a super admin', function () {
    $staff = User::factory()->create();
    $superAdmin = User::factory()->superAdmin()->create();
    UserBranchPermission::create([
        'user_id' => $staff->id,
        'branch_id' => $this->plainBranch->id,
        'permission_id' => Permission::where('name', 'bb.case.view')->value('id'),
    ]);
    grantInBranch($staff, $this->bloodBankBranch, 'bb.case.view');

    signInToBranch($this, $staff, $this->plainBranch)->getJson('/api/blood-bank/cases')->assertForbidden();
    signInToBranch($this, $superAdmin, $this->plainBranch)->getJson('/api/blood-bank/cases')->assertForbidden();
    signInToBranch($this, $staff, $this->bloodBankBranch)->getJson('/api/blood-bank/cases')->assertOk();
    signInToBranch($this, $superAdmin, $this->bloodBankBranch)->getJson('/api/blood-bank/cases')->assertOk();
});

it('leaves Blood Bank out of a super admin\'s permissions in a branch without it, and keeps every other module', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $checker = app(AccessChecker::class);

    expect($checker->permissionsIn($superAdmin, $this->plainBranch->id))
        ->not->toContain('bb.case.view')
        ->toContain(SharedPermission::BillingCreate->value)
        ->and($checker->permissionsIn($superAdmin, $this->bloodBankBranch->id))->toContain('bb.case.view');
});

it('lets a super admin switch Blood Bank on or off for a branch, and audits the change', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $update = fn (array $modules) => signInToBranch($this, $superAdmin, $this->plainBranch)
        ->putJson("/api/access/branches/{$this->plainBranch->id}", [
            'code' => $this->plainBranch->code,
            'name' => $this->plainBranch->name,
            'modules' => $modules,
        ]);

    $update(['blood-bank'])->assertOk()->assertJsonPath('data.modules', ['blood-bank']);
    $update([])->assertOk()->assertJsonPath('data.modules', []);

    $audits = AuditLog::where('action', AuditAction::BranchUpdated)->orderBy('id')->get();
    expect($audits)->toHaveCount(2)
        ->and($audits[0]->new_values['modules'])->toBe(['blood-bank'])
        ->and($audits[1]->old_values['modules'])->toBe(['blood-bank'])
        ->and($audits[1]->new_values['modules'])->toBe([]);

    $update(['billing'])->assertUnprocessable()->assertJsonValidationErrors(['modules.0']);
});
