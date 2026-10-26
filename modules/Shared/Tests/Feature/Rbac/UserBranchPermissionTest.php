<?php

use Illuminate\Testing\TestResponse;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Audit\Models\AuditLog;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Rbac\Enums\SharedPermission;
use Modules\Shared\Rbac\Models\UserBranchPermission;
use Tests\TestCase;

beforeEach(function () {
    $this->adminBranch = Branch::factory()->create();
    $this->otherBranch = Branch::factory()->create();
    $this->admin = User::factory()->create();
    $this->staff = User::factory()->create();
    grantInBranch($this->admin, $this->adminBranch, SharedPermission::UserAssignPermissions);
});

function syncPermissions(TestCase $test, User $target, Branch $branch, array $permissions): TestResponse
{
    return signInToBranch($test, $test->admin, $test->adminBranch)
        ->putJson("/api/access/users/{$target->id}/branches/{$branch->id}/permissions", ['permissions' => $permissions]);
}

it('replaces the user\'s permissions in one branch and audits what was added and removed', function () {
    grantInBranch($this->staff, $this->otherBranch, SharedPermission::UserView, SharedPermission::UserCreate);
    grantInBranch($this->staff, $this->adminBranch, SharedPermission::RoleView);

    syncPermissions($this, $this->staff, $this->otherBranch, ['sh.user.view', 'sh.user.update'])
        ->assertOk()
        ->assertJsonPath('data.branch.id', $this->otherBranch->id)
        ->assertJsonPath('data.permissions', ['sh.user.update', 'sh.user.view']);

    $audit = AuditLog::where('action', AuditAction::UserBranchPermissionsSynced)->sole();
    expect($audit->old_values['removed'])->toBe(['sh.user.create'])
        ->and($audit->new_values['added'])->toBe(['sh.user.update'])
        ->and(UserBranchPermission::where('user_id', $this->staff->id)->where('branch_id', $this->adminBranch->id)->count())->toBe(1);
});

it('returns 422 VALIDATION_FAILED for a permission that is not in the catalog', function () {
    syncPermissions($this, $this->staff, $this->otherBranch, ['cash.anything'])
        ->assertUnprocessable()
        ->assertJson(['code' => 'VALIDATION_FAILED'])
        ->assertJsonValidationErrors(['permissions.0']);
});

it('returns 422 CANNOT_CHANGE_OWN_ACCOUNT when an admin edits their own permissions', function () {
    syncPermissions($this, $this->admin, $this->adminBranch, ['sh.user.assign_permissions', 'sh.role.manage'])
        ->assertUnprocessable()
        ->assertJson(['code' => 'CANNOT_CHANGE_OWN_ACCOUNT']);
});

it('returns 403 SUPER_ADMIN_PROTECTED when an admin edits a super admin', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    syncPermissions($this, $superAdmin, $this->adminBranch, [])
        ->assertForbidden()
        ->assertJson(['code' => 'SUPER_ADMIN_PROTECTED']);
});

it('returns 403 FORBIDDEN without the assign permission in the session branch', function () {
    signInToBranch($this, $this->staff, $this->adminBranch)
        ->putJson("/api/access/users/{$this->admin->id}/branches/{$this->adminBranch->id}/permissions", ['permissions' => []])
        ->assertForbidden()
        ->assertJson(['code' => 'FORBIDDEN']);
});
