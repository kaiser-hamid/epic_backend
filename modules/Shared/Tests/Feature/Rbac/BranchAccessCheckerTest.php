<?php

use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Rbac\Actions\SyncPermissionCatalog;
use Modules\Shared\Rbac\Contracts\AccessChecker;
use Modules\Shared\Rbac\Enums\SharedPermission;
use Modules\Shared\Rbac\Models\Permission;
use Modules\Shared\Rbac\Models\Role;

beforeEach(function () {
    $this->branchA = Branch::factory()->create();
    $this->branchB = Branch::factory()->create();
    $this->user = User::factory()->create();
});

it('allows a permission only in the branch where it was granted', function () {
    grantInBranch($this->user, $this->branchA, SharedPermission::UserCreate);
    $checker = app(AccessChecker::class);

    expect($checker->allows($this->user, SharedPermission::UserCreate, $this->branchA->id))->toBeTrue()
        ->and($checker->allows($this->user, SharedPermission::UserCreate, $this->branchB->id))->toBeFalse()
        ->and($checker->allows($this->user, SharedPermission::UserUpdate, $this->branchA->id))->toBeFalse();
});

it('forbids the route in another branch session for the same permission', function () {
    grantInBranch($this->user, $this->branchA, SharedPermission::UserView);

    signInToBranch($this, $this->user, $this->branchA)->getJson('/api/access/users')->assertOk();
    signInToBranch($this, $this->user, $this->branchB)->getJson('/api/access/users')
        ->assertForbidden()
        ->assertExactJson(['message' => 'This action is unauthorized.', 'code' => 'FORBIDDEN']);
});

it('refuses records of another branch even when the user holds the permission there too', function () {
    grantInBranch($this->user, $this->branchA, SharedPermission::UserView);
    grantInBranch($this->user, $this->branchB, SharedPermission::UserView);

    $session = app('session')->driver();
    $session->put('branch_id', $this->branchA->id);
    request()->setLaravelSession($session);
    $checker = app(AccessChecker::class);

    expect($checker->allowsOnRecord($this->user, SharedPermission::UserView, $this->branchA->id))->toBeTrue()
        ->and($checker->allowsOnRecord($this->user, SharedPermission::UserView, $this->branchB->id))->toBeFalse();
});

it('grants nothing from a role whose permissions match, because roles are templates only', function () {
    app(SyncPermissionCatalog::class)->handle();
    $role = Role::factory()->create();
    $role->permissions()->sync(Permission::pluck('id'));

    expect(app(AccessChecker::class)->allows($this->user, SharedPermission::UserView, $this->branchA->id))->toBeFalse();
});

it('allows a super admin everything in every branch but nothing once deactivated', function () {
    $admin = User::factory()->superAdmin()->create();
    $checker = app(AccessChecker::class);

    expect($checker->allows($admin, SharedPermission::RoleManage, $this->branchB->id))->toBeTrue();

    $admin->forceFill(['is_active' => false])->save();

    expect($checker->allows($admin, SharedPermission::RoleManage, $this->branchB->id))->toBeFalse();
});
