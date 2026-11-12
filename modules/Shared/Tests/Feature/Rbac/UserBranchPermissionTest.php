<?php

use Illuminate\Testing\TestResponse;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Audit\Models\AuditLog;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Rbac\Enums\SharedPermission;
use Modules\Shared\Rbac\Models\Permission;
use Modules\Shared\Rbac\Models\Role;
use Modules\Shared\Rbac\Models\UserBranchPermission;
use Tests\TestCase;

beforeEach(function () {
    $this->adminBranch = Branch::factory()->create();
    $this->otherBranch = Branch::factory()->create();
    $this->admin = User::factory()->create();
    $this->staff = User::factory()->create();
    grantInBranch($this->admin, $this->adminBranch, SharedPermission::UserAssignPermissions);
});

function syncPermissions(TestCase $test, User $target, Branch $branch, array $permissions, array $extra = []): TestResponse
{
    return signInToBranch($test, $test->admin, $test->adminBranch)
        ->putJson("/api/access/users/{$target->id}/branches/{$branch->id}/permissions", ['permissions' => $permissions, ...$extra]);
}

function roleWith(string $name, string ...$permissions): Role
{
    $role = Role::factory()->create(['name' => $name]);
    $role->permissions()->sync(Permission::whereIn('name', $permissions)->pluck('id'));

    return $role;
}

it('replaces the user\'s permissions in one branch and audits what was added, removed and which roles were applied', function () {
    grantInBranch($this->staff, $this->otherBranch, SharedPermission::UserView, SharedPermission::UserCreate);
    grantInBranch($this->staff, $this->adminBranch, SharedPermission::RoleView);
    $role = roleWith('Front Desk', 'sh.user.view', 'sh.user.update');

    syncPermissions($this, $this->staff, $this->otherBranch, ['sh.user.view', 'sh.user.update'], ['applied_role_ids' => [$role->id]])
        ->assertOk()
        ->assertJsonPath('data.branch.id', $this->otherBranch->id)
        ->assertJsonPath('data.permissions', ['sh.user.update', 'sh.user.view']);

    $audit = AuditLog::where('action', AuditAction::UserBranchPermissionsSynced)->sole();
    expect($audit->old_values['removed'])->toBe(['sh.user.create'])
        ->and($audit->new_values['added'])->toBe(['sh.user.update'])
        ->and($audit->new_values['applied_roles'])->toBe(['Front Desk'])
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

it('describes each branch by the roles the user fully holds, hiding roles covered by a bigger match', function () {
    $cashier = roleWith('Cashier', 'sh.user.view', 'sh.user.create');
    roleWith('Viewer', 'sh.user.view');
    roleWith('Manager', 'sh.user.view', 'sh.role.manage');
    grantInBranch($this->staff, $this->otherBranch, SharedPermission::UserView, SharedPermission::UserCreate, SharedPermission::UserUpdate);
    grantInBranch($this->admin, $this->adminBranch, SharedPermission::UserView);

    signInToBranch($this, $this->admin, $this->adminBranch)
        ->getJson("/api/access/users/{$this->staff->id}/branch-permissions")
        ->assertOk()
        ->assertJsonPath('data.0.matched_roles', [['id' => $cashier->id, 'name' => 'Cashier']])
        ->assertJsonPath('data.0.other_permissions', ['sh.user.update']);
});

it('lists users with their permission total and the role templates they hold per branch', function () {
    $cashier = roleWith('Cashier', 'sh.user.view', 'sh.user.create');
    grantInBranch($this->staff, $this->otherBranch, SharedPermission::UserView, SharedPermission::UserCreate, SharedPermission::UserUpdate);
    grantInBranch($this->staff, $this->adminBranch, SharedPermission::RoleView);

    $response = signInToBranch($this, $this->admin, $this->adminBranch)
        ->getJson('/api/access/user-access?filter[search]='.$this->staff->employee_id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->staff->id)
        ->assertJsonPath('data.0.permission_count', 4)
        ->assertJsonStructure(['meta' => ['page_index', 'page_size', 'page_count', 'row_count']]);

    $branches = collect($response->json('data.0.branches'))->keyBy('branch.id');
    expect($branches[$this->otherBranch->id])->toMatchArray([
        'permission_count' => 3,
        'matched_roles' => [['id' => $cashier->id, 'name' => 'Cashier']],
        'other_permission_count' => 1,
    ])->and($branches[$this->adminBranch->id])->toMatchArray([
        'permission_count' => 1,
        'matched_roles' => [],
        'other_permission_count' => 1,
    ]);
});

it('returns 403 FORBIDDEN on the user access list without the assign permission', function () {
    signInToBranch($this, $this->staff, $this->adminBranch)
        ->getJson('/api/access/user-access')
        ->assertForbidden()
        ->assertJson(['code' => 'FORBIDDEN']);
});

it('lets user viewers load role and permission options without holding role permissions', function () {
    grantInBranch($this->admin, $this->adminBranch, SharedPermission::UserView);

    signInToBranch($this, $this->admin, $this->adminBranch)->getJson('/api/access/role-options')->assertOk();
    signInToBranch($this, $this->admin, $this->adminBranch)->getJson('/api/access/permission-options')->assertOk();

    signInToBranch($this, $this->staff, $this->adminBranch)->getJson('/api/access/role-options')->assertForbidden();
    signInToBranch($this, $this->staff, $this->adminBranch)->getJson('/api/access/permission-options')->assertForbidden();
});
